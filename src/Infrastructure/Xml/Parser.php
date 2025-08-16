<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Xml;

use Deprecated;
use DOMNode;
use DOMElement;
use DOMDocument;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;

/**
 * Use to parse XML response into Container
 */
class Parser
{
    public function __construct(protected readonly DOMDocument $dom)
    {
        $this->dom->encoding = 'UTF-8';
        $this->dom->formatOutput = true;
        $this->dom->xmlVersion = '1.0';
        $this->dom->preserveWhiteSpace = false;
    }

    /**
     * Parse XML response into Container
     *
     * @param string $xmlContent xml string to process
     * @return Container contains php types
     */
    public static function fromXMLString(string $xmlContent): Container
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($xmlContent, LIBXML_NOBLANKS | LIBXML_NOCDATA);
        libxml_use_internal_errors(false);
        return (new self($dom))->parseNode($dom->documentElement);
    }


    #[Deprecated('Config files should be .json')]
    public static function fromXMLFile(string $fileName): Container
    {
        return (new self(new DOMDocument()))->parseXml($fileName);
    }

    #[Deprecated('Config files should be .json')]
    public function parseXml(string $fileName): Container
    {
        # TmController/public/xml/$fileName.xml
        $xmlFile = Aseco::path()
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'xml'
            . DIRECTORY_SEPARATOR
            . $fileName
            . '.xml';

        libxml_use_internal_errors(true);
        libxml_clear_errors();

        $loaded = $this->dom->load($xmlFile, LIBXML_NOBLANKS | LIBXML_NOCDATA);

        if (!$loaded) {
            $err  = libxml_get_errors();
            libxml_clear_errors();
            $errMessages = array_map(function ($err) {
                return sprintf(
                    'Line %d, Column %d: %s',
                    $err->line,
                    $err->column,
                    $err->message
                );
            }, $err);
            throw new \Exception(
                sprintf('Failed to load XML file "%s": %s', $xmlFile, implode("\n", $errMessages))
            );
        }

        libxml_use_internal_errors(false);

        $root = $this->dom->documentElement;
        $parsed = $this->parseNode($root);

        return $parsed;
    }

    /**
     * Parse xml nodes
     *
     * @param DOMElement|DOMNode $node
     * @return mixed
     */
    protected function parseNode(DOMElement|DOMNode $node): mixed
    {
        $container = new Container();
        $elementCount = []; // Track element occurrences by name
        $textContent = [];

        if ($node instanceof DOMElement) {
            foreach ($node->attributes as $attribute) {
                $container->set($attribute->nodeName, $this->convertValues($attribute->nodeValue));
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $childName = $child->nodeName;
                $childContainer = $this->parseNode($child);

                if (isset($elementCount[$childName])) {
                    $elementCount[$childName]++;
                    $newChildName = $childName . '_' . $elementCount[$childName];
                } else {
                    $elementCount[$childName] = 1;
                    $newChildName = $childName;
                }

                if ($container->offsetExists($newChildName)) {
                    // If it exists, store the values in an array
                    $existingValue = $container->get($newChildName);
                    if (!is_array($existingValue)) {
                        $existingValue = [$existingValue]; // Convert to array if not already
                    }
                    $existingValue[] = $childContainer;
                    $container->set($newChildName, $existingValue);
                } else {
                    $container->set($newChildName, $childContainer);
                }
            } elseif ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
                $text = trim($child->nodeValue);
                if ($text !== '') {
                    $textContent[] = $text;
                }
            }
        }

        // If we have accumulated text content, store it as well
        if (!empty($textContent)) {
            $combined = implode(' ', $textContent);
            if ($container->count() === 0) {
                return $this->convertValues($combined);
            } else {
                $container->set('#text', $combined);
            }
        }

        return $container;
    }

    /**
     * Convert XML values into PHP
     *
     * @param mixed $value
     * @return mixed
     */
    protected function convertValues(mixed $value): mixed
    {
        if (is_string($value)) {
            $filerBool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($filerBool !== null) {
                return $filerBool;
            }

            if (is_numeric($value)) {
                return strpos($value, '.') !== false ? (float)$value : (int)$value;
            }

            if (Aseco::isBase64($value)) {
                return base64_decode($value);
            }

            if ($value === '') {
                return null;
            }

            if ($this->isDate($value)) {
                return new \DateTime($value);
            }
        }

        return $value;
    }

    /**
     * Helper for date
     *
     * @param string $value
     * @return boolean
     */
    protected function isDate(string $value): bool
    {
        // regex for date
        $pattern = '/^\d{4}-\d{2}-\d{2}(?:[ T]\d{2}:\d{2}:\d{2})?$/';
        return preg_match($pattern, $value) === 1;
    }
}
