<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services;

use CurlHandle;
use Yuhzel\TmController\App\Aseco;

class HttpClient
{
    /**
     * @var CurlHandle cURL handle.
     */
    private CurlHandle $ch;

    /**
     * @var string Path to the certificate file.
     */
    private string $cert = '';

    /**
     * @var string Path to the file where cookies are stored.
     */
    private string $cookieFile = '';

    public function __construct()
    {
        $this->cert = Aseco::path() . 'public/cacert.pem';
        $this->cookieFile = Aseco::path() . 'public/cookies.txt';
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_CAINFO, $this->cert);
        $this->setTimeout(10); // Default timeout
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, $this->cookieFile); // Save cookies
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $this->cookieFile); // Reuse cookies
        curl_setopt($this->ch, CURLOPT_ENCODING, ''); // Automatically handle all encodings
        curl_setopt($this->ch, CURLOPT_FORBID_REUSE, false); // Enable keep-alive
        curl_setopt($this->ch, CURLOPT_TCP_KEEPALIVE, 1);   // Enable TCP keep-alive probes
        curl_setopt($this->ch, CURLOPT_TCP_KEEPIDLE, 60);   // Start keep-alive probes after 60 seconds
        curl_setopt($this->ch, CURLOPT_TCP_KEEPINTVL, 10);  // Send keep-alive probes every 10 seconds
        curl_setopt($this->ch, CURLOPT_KEEP_SENDING_ON_ERROR, true);
    }

    public function get(
        string $endpoint,
        array $params = [],
        array $headers = []
    ): string|bool {
        return $this->request('GET', $endpoint, $params, $headers);
    }

    public function post(
        string $endpoint,
        string|array $data = [],
        array $headers = []
    ): string|bool {
        return $this->request('POST', $endpoint, $data, $headers);
    }


    public function put(
        string $endpoint,
        array $data = [],
        array $headers = []
    ): string|bool {
        return $this->request('PUT', $endpoint, $data, $headers);
    }

    public function delete(
        string $endpoint,
        array $data = [],
        array $headers = []
    ): string|bool {
        return $this->request('DELETE', $endpoint, $data, $headers);
    }

    public function setTimeout(int $seconds): void
    {
        curl_setopt($this->ch, CURLOPT_TIMEOUT, $seconds);
    }

    public function alive(string $endpoint = ''): bool
    {
        curl_setopt($this->ch, CURLOPT_URL, $endpoint);

        // Set to only attempt a connection without fetching the body
        curl_setopt($this->ch, CURLOPT_NOBODY, true);
        curl_setopt($this->ch, CURLOPT_CONNECT_ONLY, true);

        // Attempt the connection
        $result = curl_exec($this->ch);

        // Reset CURLOPT_NOBODY to avoid affecting subsequent requests
        curl_setopt($this->ch, CURLOPT_NOBODY, false);

        // Check for connection errors
        if (curl_errno($this->ch)) {
            Aseco::console('Connection check error: ' . curl_error($this->ch));
            return false;
        }

        return $result !== false;
    }

    public function close(): void
    {
        curl_close($this->ch);
    }

    public function __destruct()
    {
        $this->close();
    }

    private function request(
        string $method,
        string $endpoint,
        string|array $params = [],
        array $headers = []
    ): string|bool {

        // Handle GET requests with query parameters
        if ($method === 'GET' && !empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        curl_setopt($this->ch, CURLOPT_URL, $endpoint);
        curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);

        // Handle POST, PUT, DELETE payload
        if ($method === "POST" && is_string($params)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
        } elseif (in_array($method, ["PUT", "DELETE"], true) && is_array($params)) {
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        // Set custom headers if provided
        if (!empty($headers)) {
            curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            Aseco::console($endpoint . 'curl error' . curl_error($this->ch));
            return false;
        }

        $httpCode = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            Aseco::console($endpoint . 'HTTP error: ' . $httpCode);
            return false;
        }

        return $response;
    }
}
