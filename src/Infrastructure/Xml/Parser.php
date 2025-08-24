<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Xml;

use Deprecated;
use DOMNode;
use DOMElement;
use DOMDocument;
use Yuhzel\TmController\App\Service\{Aseco, Server};
use Yuhzel\TmController\Core\TmContainer;

/**
 * Use to parse XML response into TmContainer
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
     * Parse XML response into TmContainer
     *
     * @param string $xmlContent xml string to process
     * @return TmContainer contains php types
     */
    public static function fromXMLString(string $xmlContent): TmContainer
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadXML($xmlContent, LIBXML_NOBLANKS | LIBXML_NOCDATA);
        libxml_use_internal_errors(false);
        return (new self($dom))->parseNode($dom->documentElement);
    }


    #[Deprecated('Config files should be .json')]
    public static function fromXMLFile(string $fileName): TmContainer
    {
        return (new self(new DOMDocument()))->parseXml($fileName);
    }

    #[Deprecated('Config files should be .json')]
    public function parseXml(string $fileName): TmContainer
    {
        # TmController/public/xml/$fileName.xml
        $xmlFile =
            Server::$publicDir
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
        $TmContainer = new TmContainer();
        $elementCount = []; // Track element occurrences by name
        $textContent = [];

        if ($node instanceof DOMElement) {
            foreach ($node->attributes as $attribute) {
                $TmContainer->set($attribute->nodeName, $this->convertValues($attribute->nodeValue));
            }
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $childName = $child->nodeName;
                $childTmContainer = $this->parseNode($child);

                if (isset($elementCount[$childName])) {
                    $elementCount[$childName]++;
                    $newChildName = $childName . '_' . $elementCount[$childName];
                } else {
                    $elementCount[$childName] = 1;
                    $newChildName = $childName;
                }

                if ($TmContainer->offsetExists($newChildName)) {
                    // If it exists, store the values in an array
                    $existingValue = $TmContainer->get($newChildName);
                    if (!is_array($existingValue)) {
                        $existingValue = [$existingValue]; // Convert to array if not already
                    }
                    $existingValue[] = $childTmContainer;
                    $TmContainer->set($newChildName, $existingValue);
                } else {
                    $TmContainer->set($newChildName, $childTmContainer);
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
            if ($TmContainer->count() === 0) {
                return $this->convertValues($combined);
            } else {
                $TmContainer->set('#text', $combined);
            }
        }

        return $TmContainer;
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
