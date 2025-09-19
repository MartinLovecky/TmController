<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Xml;

use DOMNode;
use DOMElement;
use DOMDocument;
use Yuhzel\TmController\App\Service\Arr;
use Yuhzel\TmController\App\Service\Log;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Infrastructure\Xml\CallbackParamHelper;

/**
 * This class handles parsing of XML-RPC responses.
 * It supports both single and multi-call responses and maps them into TmContainer objects.
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
     * Parses an XML-RPC method call into a TmContainer structure.
     *
     * @param string $xml XML string representing the method call
     * @param bool $multicall set true to procces multiCall
     * @return TmContainer Parsed container with methodName and params
     * @throws \Exception If parsing fails
     */
    public function parseMethodCall(string $xml, bool $multicall = false): TmContainer
    {
        if (!$this->dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOWARNING)) {
            throw new \Exception("Failed to parse XML method call.");
        }

        $container = new TmContainer();
        $root = $this->dom->documentElement;

        if ($root->tagName !== 'methodCall') {
            throw new \Exception("Expected <methodCall> root element.");
        }

        $methodNameElement = $this->getFirstDirectChild($root, 'methodName');
        if (!$methodNameElement) {
            throw new \Exception("Missing <methodName> element in method call.");
        }

        $methodName = trim($methodNameElement->textContent ?? '');
        $container->set('methodName', $methodName);
        $paramsElement = $this->getFirstDirectChild($root, 'params');

        if (!$paramsElement) {
            return $container;
        }

        if ($multicall) {
            return $this->processMultiCallParams($paramsElement, $container);
        }

        $paramElements = $this->getDirectChildren($paramsElement, 'param');
        // Ensure mapping exists or is temporarily filled
        $paramNames = CallbackParamHelper::getNamedParams($methodName);

        foreach ($paramElements as $index => $param) {
            // index 3 is not usefull at all skip it
            if ($index === 3) {
                continue;
            } $valueElement = $this->getFirstDirectChild($param, 'value');

            $value = $this->processValue($valueElement?->firstChild);

            // Use friendly name if available, otherwise fallback to numeric index
            $path = $paramNames[$index] ?? (string)$index;
            $container->set($path, $value);
            if (!isset($paramNames[$index])) { // Log to implemt it
                Log::warning(
                    "Discovered unknown callback parameter for $methodName at index $index",
                    ['value' => $value],
                    $methodName
                );
                $paramNames[$index] = "param$index";
                CallbackParamHelper::setMapping($methodName, $paramNames);
            }
        }
        return $container;
    }

    /**
     * Parses an XML-RPC response into a TmContainer structure.
     *
     * @param string $methodName Name of the method being responded to
     * @param string $xml XML response content
     * @param bool $multicall set true to procces multiCallRequest
     * @return TmContainer Parsed container object with the response data
     * @throws \Exception If the XML is malformed or missing required elements
     */
    public function parseResponse(string $methodName, string $xml, bool $multicall = false): TmContainer
    {
        $this->methodName = $methodName;

        if (!$this->dom->loadXML($xml, LIBXML_NOCDATA | LIBXML_NOWARNING)) {
            throw new \Exception("Failed to parse XML response for, {$this->methodName}");
        }

        $container = new TmContainer();
        $container->set('methodName', $this->methodName);

        $root = $this->dom->documentElement;
        $fault = $this->getFirstDirectChild($root, 'fault');

        if ($fault) {
            return $this->processFault($fault, $container);
        }

        $params = $this->getFirstDirectChild($root, 'params');

        if ($params) {
            return $multicall
            ? $this->processMultiCallParams($params, $container)
            : $this->processParams($params, $container);
        }

        throw new \Exception("Response parsing failed for {$this->methodName}: No recognizable elements found.");
    }

    /**
     * Processes a fault response and extracts faultCode and faultString.
     *
     * @param DOMElement $fault The <fault> element
     * @param TmContainer $container TmContainer to update with fault data
     * @return TmContainer TmContainer with fault details set
     */
    protected function processFault(DOMElement $fault, TmContainer $container): TmContainer
    {
        $valueElement = $this->getFirstDirectChild($fault, 'value');
        if ($valueElement) {
            $faultStruct = $this->processValue($valueElement?->firstChild);
            if ($faultStruct instanceof TmContainer) {
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
     * @param TmContainer $container TmContainer to populate with response data
     * @return TmContainer Updated container with parsed parameter values
     */
    protected function processParams(DOMElement $params, TmContainer $container): TmContainer
    {
        $paramElements = $this->getDirectChildren($params, 'param');
        if (count($paramElements) === 1) {
            $valueElement = $this->getFirstDirectChild($paramElements[0], 'value');
            $processedValue = $this->processValue($valueElement?->firstChild);

            return $processedValue instanceof TmContainer
            ? $container->merge($processedValue)
            : $container->set('value', $processedValue);
        }

        $paramsTmContainer = new TmContainer();
        foreach ($paramElements as $index => $param) {
            $valueElement = $this->getFirstDirectChild($param, 'value');
            $paramsTmContainer->set((string)$index, $this->processValue($valueElement?->firstChild));
        }

        return $container->set('params', $paramsTmContainer);
    }

    /**
     * Processes an individual <value> node based on XML-RPC type.
     *
     * @param DOMNode|null $node The <value> node
     * @return mixed Parsed PHP value (e.g., string, int, array, TmContainer, etc.)
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
            'fault' => $this->processFault($node, new TmContainer()),
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
        $dataElement = $this->getFirstDirectChild($element, 'data');
        if (!$dataElement) {
            return [];
        }

        foreach ($this->getDirectChildren($dataElement, 'value') as $value) {
            $data[] = $this->processValue($value?->firstChild);
        }

        return $data;
    }

    /**
     * Processes a <struct> element and returns a mapped TmContainer.
     *
     * @param DOMElement $element <struct> element to convert
     * @param string $parentPath Used internally for dot-prefixed keys
     * @return TmContainer TmContainer populated with struct members
     */
    protected function processStruct(DOMElement $element, string $parentPath = ''): TmContainer
    {
        $container = new TmContainer();

        foreach ($this->getDirectChildren($element, 'member') as $member) {
            $nameNode = $this->getFirstDirectChild($member, 'name');
            $valueNode = $this->getFirstDirectChild($member, 'value');
            if (!$nameNode || !$valueNode) {
                continue;
            }
            $this->setStructValue(
                $container,
                trim($nameNode->textContent ?? ''),
                $this->processValue($valueNode->firstChild),
                $parentPath
            );
        }

        return $container;
    }

    /**
     * Helper method
     *
     * @param TmContainer $container
     * @param string $name
     * @param mixed $value
     * @param string $parentPath
     * @return void
     */
    protected function setStructValue(TmContainer $container, string $name, mixed $value, string $parentPath = ''): void
    {
        $fullPath = $parentPath ? "$parentPath.$name" : $name;

        if ($value instanceof TmContainer) {
            foreach ($value->toArray() as $childKey => $childValue) {
                $container->set("$fullPath.$childKey", $childValue);
            }
            return;
        }

        $container->set($fullPath, $value);
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

    /**
     * Processes system.multicall responses and returns them in a container.
     *
     * @param DOMElement $params The <params> element containing multiple responses
     * @param TmContainer $container TmContainer to populate with the responses
     * @return TmContainer TmContainer with responses keyed by index
     */
    protected function processMultiCallParams(DOMElement $params, TmContainer $container): TmContainer
    {
        $paramElements = $this->getDirectChildren($params, 'param');

        if (count($paramElements) !== 1) {
            return $container->set('results', []);
        }

        $valueElement = $this->getFirstDirectChild($paramElements[0], 'value');
        $outerArray   = $this->processValue($valueElement?->firstChild);
        $result = ['results' => Arr::flatten($outerArray)];
        return TmContainer::fromArray($result);
    }
}
