<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Xml;

use DOMElement;
use DOMDocument;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Services\Arr;

/**
 * Request is responsible for creating XML-RPC
 * @author Yuhzel <yuhzel@gmail.com>
 */
class Request
{
    protected DOMDocument $dom;

    /**
     * Creates an XML-RPC request for a single method call.
     *
     * @param string $methodName Name of the method to call
     * @param array $args Arguments to pass to the method
     * @return string XML-RPC string representing the request
    */
    public function createRpcRequest(string $methodName, array $args): string
    {
        // its important that each request is new DOMDocument do not change this
        $this->dom = new DOMDocument();
        $this->dom->encoding = 'UTF-8';
        $this->dom->formatOutput = true;
        $this->dom->xmlVersion = '1.0';
        $this->dom->preserveWhiteSpace = false;
        $methodCall = $this->dom->createElement('methodCall');
        $this->dom->appendChild($methodCall);
        $methodName = $this->dom->createElement('methodName', htmlspecialchars($methodName, ENT_XML1, 'UTF-8'));
        $methodCall->appendChild($methodName);
        $params = $this->dom->createElement('params');
        $methodCall->appendChild($params);

        foreach ($args as $arg) {
            $this->addParam($arg, $params);
        }

        $save = $this->dom->saveXML();

        return $save;
    }

    /**
     * Creates an XML-RPC request for multiple method calls using system.multicall.
     *
     * @param array $calls Array of calls with 'methodName' and 'params'
     * @return string XML string representing the multi-call request
    */
    public function createMultiCallRequest(array $calls): string
    {
        $this->dom = new DOMDocument();
        $this->dom->encoding = 'UTF-8';
        $this->dom->formatOutput = true;
        $this->dom->xmlVersion = '1.0';
        $this->dom->preserveWhiteSpace = false;

        $methodCall = $this->dom->createElement('methodCall');
        $this->dom->appendChild($methodCall);

        $methodName = $this->dom->createElement('methodName', 'system.multicall');
        $methodCall->appendChild($methodName);

        $params = $this->dom->createElement('params');
        $methodCall->appendChild($params);

        $param = $this->dom->createElement('param');
        $value = $this->dom->createElement('value');
        $array = $this->dom->createElement('array');
        $data = $this->dom->createElement('data');

        foreach ($calls as $call) {
            $callStruct = $this->dom->createElement('struct');

            $memberMethod = $this->dom->createElement('member');
            $memberMethod->appendChild($this->dom->createElement('name', 'methodName'));

            $methodValue = $this->dom->createElement('value');
            $methodValue->appendChild($this->createStringElement($call['methodName']));
            $memberMethod->appendChild($methodValue);
            $callStruct->appendChild($memberMethod);

            $memberParams = $this->dom->createElement('member');
            $memberParams->appendChild($this->dom->createElement('name', 'params'));

            $paramsValue = $this->dom->createElement('value');
            $paramsArray = $this->dom->createElement('array');
            $paramsData = $this->dom->createElement('data');

            foreach ($call['params'] as $arg) {
                $val = $this->dom->createElement('value');
                $val->appendChild($this->determineType(gettype($arg), $arg));
                $paramsData->appendChild($val);
            }

            $paramsArray->appendChild($paramsData);
            $paramsValue->appendChild($paramsArray);
            $memberParams->appendChild($paramsValue);
            $callStruct->appendChild($memberParams);

            $valueItem = $this->dom->createElement('value');
            $valueItem->appendChild($callStruct);
            $data->appendChild($valueItem);
        }

        $array->appendChild($data);
        $value->appendChild($array);
        $param->appendChild($value);
        $params->appendChild($param);

        return $this->dom->saveXML();
    }

    /**
     * Adds a parameter to the <params> element in the XML.
     *
     * @param mixed $arg Parameter value
     * @param DOMElement $paramsElement Parent <params> DOM element
     * @return void
    */
    protected function addParam(mixed $arg, DOMElement $paramsElement): void
    {
        $param = $this->dom->createElement('param');
        $valueElement = $this->dom->createElement('value');
        $typeElement = $this->determineType(gettype($arg), $arg);
        $valueElement->appendChild($typeElement);
        $param->appendChild($valueElement);
        $paramsElement->appendChild($param);
    }

    /**
     * Converts a PHP value to an appropriate XML-RPC type element.
     *
     * @param string $type PHP type name (string, array, object, etc.)
     * @param mixed $value Value to convert
     * @return DOMElement XML element representing the value
    */
    protected function determineType(string $type, mixed $value): DOMElement
    {
        if ($value instanceof \DateTimeInterface) {
            return $this->dom->createElement('dateTime.iso8601', $value->format('Ymd\TH:i:s'));
        }

        if (is_resource($value)) {
            return $this->dom->createElement('base64', base64_encode(stream_get_contents($value)));
        }

        if (is_string($value) && Aseco::isBase64($value)) {
            return $this->dom->createElement('base64', base64_encode($value));
        }

        if ($type === 'array' && Arr::isAssoc($value)) {
            return $this->structToXmlElement((object)$value);
        }

        return match ($type) {
            'string'   => $this->createStringElement($value),
            'boolean'  => $this->dom->createElement('boolean', $value ? '1' : '0'),
            'integer'  => $this->dom->createElement('int', htmlspecialchars((string)$value, ENT_XML1, 'UTF-8')),
            'double'   => $this->dom->createElement('double', number_format($value, 1, '.', '')),
            'array'    => $this->buildArrayElement($value),
            'object'   => $this->structToXmlElement($value),
            'NULL'     => $this->dom->createElement('nil'),//$this->dom->createElement('string', 'null'),
            default    => $this->dom->createElement('nil')//$this->dom->createElement('string', 'null')
        };
    }

    /**
     * Creates a <string> element and uses CDATA if it contains a <manialink> tag.
     *
     * @param string $value String value to encode
     * @return DOMElement <string> element with text or CDATA
    */
    protected function createStringElement(string $value): DOMElement
    {
        $stringElement = $this->dom->createElement('string');
        if (strpos($value, '<manialink') !== false) {
            $cdata = $this->dom->createCDATASection($value);
            $stringElement->appendChild($cdata);
            return $stringElement;
        } else {
            $stringElement->appendChild($this->dom->createTextNode($value));
        }

        return $stringElement;
    }

    /**
     * Builds an <array> element from a PHP array.
     *
     * @param array $array PHP array to convert
     * @return DOMElement <array> element with child values
    */
    protected function buildArrayElement(array $array): DOMElement
    {
        $arrayElement = $this->dom->createElement('array');
        $dataElement = $this->dom->createElement('data');

        foreach ($array as $value) {
            $valueElement = $this->dom->createElement('value');

            if (is_array($value)) {
                $typeElement = Arr::isAssoc($value)
                ? $this->structToXmlElement((object)$value)
                : $this->buildArrayElement($value);
            } elseif (is_object($value)) {
                $typeElement = $this->structToXmlElement($value);
            } else {
                $typeElement = $this->determineType(gettype($value), $value);
            }

            $valueElement->appendChild($typeElement);
            $dataElement->appendChild($valueElement);
        }

        // Prevent <data/> by forcing <data></data>
        if ($dataElement->childNodes->length === 0) {
            $dataElement->appendChild($this->dom->createTextNode(''));
        }

        $arrayElement->appendChild($dataElement);
        return $arrayElement;
    }

    /**
     * Converts a PHP object (or associative array) to a <struct> element.
     *
     * @param object $object Object or converted array to structure
     * @return DOMElement <struct> element with members
    */
    protected function structToXmlElement(object $object): DOMElement
    {
        $structElement = $this->dom->createElement('struct');

        foreach (get_object_vars($object) as $key => $value) {
            $memberElement = $this->dom->createElement('member');
            $nameElement = $this->dom->createElement('name', htmlspecialchars((string)$key));
            $valueElement = $this->dom->createElement('value');

            if (is_object($value)) {
                $typeElement = $this->structToXmlElement($value);
            } elseif (is_array($value) && Arr::isAssoc($value)) {
                $typeElement = $this->structToXmlElement((object)$value);
            } elseif (is_array($value)) {
                $typeElement = $this->buildArrayElement($value);
            } else {
                $typeElement = $this->determineType(gettype($value), $value);
            }

            $valueElement->appendChild($typeElement);
            $memberElement->appendChild($nameElement);
            $memberElement->appendChild($valueElement);
            $structElement->appendChild($memberElement);
        }

        return $structElement;
    }
}
