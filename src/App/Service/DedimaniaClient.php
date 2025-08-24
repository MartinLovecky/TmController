<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

use Yuhzel\TmController\App\Service\{Aseco, HttpClient};
use Yuhzel\TmController\Infrastructure\Xml\{Request, Response};

class DedimaniaClient
{
    private string $endpoint = 'http://dedimania.net:8002/Dedimania';

    public function __construct(
        protected Request $request,
        protected Response $response,
        protected HttpClient $httpClient
    ) {
    }

    public function request(string $type, array $params): mixed
    {
        $xml = $this->request->createMultiCallRequest($params);

        $headers = [
            'User-Agent: XMLaccess',
            'Cache-Control: no-cache',
            'Content-Type: text/xml; charset=UTF-8',
            'Accept-Encoding: gzip, deflate',
            'Content-Length: ' . strlen($xml),
            'Keep-Alive: timeout=600, max=2000',
            'Connection: Keep-Alive',
        ];

        $response = $this->httpClient->post($this->endpoint, $xml, $headers);

        if (!$response) {
            return false;
        }

        $parsed = $this->response->parseResponse($type, $response, true);

        if ($parsed->has('results.1.faultString')) {
            Aseco::console("Dedimania request {$type} fault {$parsed->get('results.1.faultString')}");
            return false;
        }
        return $parsed->get('results');
    }
}
