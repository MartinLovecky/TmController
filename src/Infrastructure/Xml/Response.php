<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Xml;

use DOMNode;
use DOMElement;
use DOMDocument;
use Yuhzel\TmController\Core\Container;

/**
 * This class handles parsing of XML-RPC responses.
 * It supports both single and multi-call responses and maps them into Container objects.
 *
 * @author Yuhzel <yuhzel@gmail.com>
 */
class Response
{
    private string $methodName = '';

    /**
     * Constructor to initialize and configure the DOMDocument.
     *
     * @param DOMDocument $dom An empty DOMDocument instance for internal use
     */
    public function __construct(protected DOMDocument $dom)
    {
        $this->dom->encoding = 'UTF-8';
        $this->dom->formatOutput = true;
        $this->dom->xmlVersion = '1.0';
        $this->dom->preserveWhiteSpace = false;
    }

    /**
     * Parses an XML-RPC response into a Container structure.
     *
     * @param string $methodName Name of the method being responded to
     * @param string $xml XML response content
     * @param bool $readonly Whether to lock the resulting container for further mutation
     * @return Container Parsed container object with the response data
     * @throws \Exception If the XML is malformed or missing required elements
     */
    public function parseResponse(string $methodName, string $xml, bool $readonly): Container
    {
        $this->methodName = $methodName;

        if (!$this->dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOWARNING)) {
            throw new \Exception("Failed to parse XML response for, {$this->methodName}");
        }

        $container = new Container();
        $container->set('methodName', $this->methodName);

        $root = $this->dom->documentElement;

        if ($fault = $this->getFirstDirectChild($root, 'fault')) {
            $container = $this->processFault($fault, $container);
        } elseif ($params = $this->getFirstDirectChild($root, 'params')) {
            if ($this->methodName === 'system.multicall') {
                $container = $this->processMultiCallParams($params, $container);
            } else {
                $container = $this->processParams($params, $container);
            }
        } if (!$fault && !$params) {
            throw new \Exception("Response parsing failed for {$this->methodName}: No recognizable elements found.");
        }

        if ($readonly) {
            return $container->setReadonly();
        }

        return $container;
    }

    /**
     * Processes a fault response and extracts faultCode and faultString.
     *
     * @param DOMElement $fault The <fault> element
     * @param Container $container Container to update with fault data
     * @return Container Container with fault details set
     */
    protected function processFault(DOMElement $fault, Container $container): Container
    {
        if ($valueElement = $this->getFirstDirectChild($fault, 'value')) {
            $faultStruct = $this->processValue($valueElement?->firstChild);

            if ($faultStruct instanceof Container) {
                return $container
                    ->set('#err.faultCode', $faultStruct->get('faultCode', -1))
                    ->set('#err.faultString', $faultStruct->get('faultString', 'Unknown fault'));
            }
        }

        return $container
            ->set('#err.faultCode', -1)
            ->set('#err.faultString', 'Invalid fault structure');
    }

    /**
     * Processes a standard <params> response.
     *
     * @param DOMElement $params The <params> element
     * @param Container $container Container to populate with response data
     * @return Container Updated container with parsed parameter values
     */
    protected function processParams(DOMElement $params, Container $container): Container
    {
        $paramElements = $this->getDirectChildren($params, 'param');

        if (count($paramElements) === 1) {
            $valueElement = $this->getFirstDirectChild($paramElements[0], 'value');
            $processedValue = $this->processValue($valueElement?->firstChild);

            return $processedValue instanceof Container
            ? $container->merge($processedValue)
            : $container->set('value', $processedValue);
        }

        $paramsContainer = new Container();
        foreach ($paramElements as $index => $param) {
            $valueElement = $this->getFirstDirectChild($param, 'value');
            $paramsContainer->set((string)$index, $this->processValue($valueElement?->firstChild));
        }

        return $container->set('params', $paramsContainer);
    }

    /**
     * Processes an individual <value> node based on XML-RPC type.
     *
     * @param DOMNode|null $node The <value> node
     * @return mixed Parsed PHP value (e.g., string, int, array, Container, etc.)
     * @throws \Exception If the type is unknown
     */
    protected function processValue(?DOMNode $node): mixed
    {
        if (!$node || !$node instanceof DOMElement) {
            return $node ? trim($node->textContent ?? '') : null;
        }

        return match ($node->tagName) {
            'string' => trim($node->textContent ?? ''),
            'boolean' => filter_var($node->textContent, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            'int', 'i4' => filter_var($node->textContent, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'double' => filter_var($node->textContent, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE),
            'array' => $this->processArray($node),
            'struct' => $this->processStruct($node),
            'dateTime.iso8601' => new \DateTime($node->textContent),
            'base64' => base64_decode($node->textContent),
            'nil' => null,
            'fault' => $this->processFault($node, new Container()),
            default => throw new \Exception("Unknown element type '{$node->tagName}' in value processing.")
        };
    }

    /**
     * Processes an <array> element and returns the corresponding PHP array.
     *
     * @param DOMElement $element <array> DOM element
     * @return array PHP array with parsed values
     */
    protected function processArray(DOMElement $element): array
    {
        $data = [];
        if ($dataElement = $this->getFirstDirectChild($element, 'data')) {
            foreach ($this->getDirectChildren($dataElement, 'value') as $value) {
                $data[] = $this->processValue($value?->firstChild);
            }
        }

        return $data;
    }

    /**
     * Processes a <struct> element and returns a mapped Container.
     *
     * @param DOMElement $element <struct> element to convert
     * @param string $parentPath Used internally for dot-prefixed keys
     * @return Container Container populated with struct members
     */
    protected function processStruct(DOMElement $element, string $parentPath = ''): Container
    {
        $container = new Container();

        foreach ($this->getDirectChildren($element, 'member') as $member) {
            $nameNode = $this->getFirstDirectChild($member, 'name');
            $valueNode = $this->getFirstDirectChild($member, 'value');

            if ($nameNode && $valueNode) {
                $name = trim($nameNode->textContent ?? '');
                $value = $this->processValue($valueNode->firstChild);

                $fullPath = $parentPath ? $parentPath . '.' . $name : $name;

                if ($value instanceof Container) {
                    foreach ($value->toArray() as $childKey => $childValue) {
                        $container->set($fullPath . '.' . $childKey, $childValue);
                    }
                } else {
                    $container->set($fullPath, $value);
                }
            }
        }

        return $container;
    }

    /**
     * Processes system.multicall responses and returns them in a container.
     *
     * @param DOMElement $params The <params> element containing multiple responses
     * @param Container $container Container to populate with the responses
     * @return Container Container with responses keyed by index
     */
    protected function processMultiCallParams(DOMElement $params, Container $container): Container
    {
        $responses = [];

        $param = $this->getFirstDirectChild($params, 'param');
        $value = $this->getFirstDirectChild($param, 'value');
        $array = $this->getFirstDirectChild($value, 'array');
        $data = $this->getFirstDirectChild($array, 'data');

        foreach ($this->getDirectChildren($data, 'value') as $index => $valueElement) {
            $child = $valueElement->firstChild;

            if (!$child instanceof DOMElement) {
                $responses[$index] = null;
                continue;
            }

            if ($child->tagName === 'array') {
                $innerData = $this->getFirstDirectChild($child, 'data');
                $result = [];
                foreach ($this->getDirectChildren($innerData, 'value') as $resultVal) {
                    $result[] = $this->processValue($resultVal->firstChild);
                }
                $responses[$index] = $result;
            } elseif ($child->tagName === 'struct') {
                $fault = $this->processStruct($child);
                $responses[$index] = [
                'faultCode' => $fault->get('faultCode', -1),
                'faultString' => $fault->get('faultString', 'Unknown fault'),
                ];
            } else {
                $responses[$index] = $this->processValue($child);
            }
        }

        return $container->set('responses', $responses);
    }

    /**
     * Efficiently gets the first direct child element with the given tag name.
     *
     * @param DOMElement $parent Parent element
     * @param string $tagName Child tag name to search for
     * @return DOMElement|null First matching child element or null if none found
     */
    protected function getFirstDirectChild(DOMElement $parent, string $tagName): ?DOMElement
    {
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === $tagName) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Returns all direct child elements of a parent with a given tag name.
     *
     * @param DOMElement $parent Parent element
     * @param string $tagName Tag name to search for
     * @return DOMElement[] Array of matched child elements
     */
    protected function getDirectChildren(DOMElement $parent, string $tagName): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->tagName === $tagName) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
