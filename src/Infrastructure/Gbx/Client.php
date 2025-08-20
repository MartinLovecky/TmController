<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Gbx;

use Deprecated;
use Exception;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Socket;
use Yuhzel\TmController\Infrastructure\Xml\{Request, Response};

class Client
{
    public string $methodName = '';
    private int $reqHandle = 0x80000000;
    private const int MAX_REQ_SIZE = 512 * 1024 - 8;
    private const int MAX_RES_SIZE = 4096 * 1024;
    private const float TIMEOUT = 20.0;
    /** @var Container[] */
    private array $cb_message = [];
    public array $calls = [];

    public function __construct(
        protected Socket $socket,
        protected Request $request,
        protected Response $response
    ) {
        $this->init();
        $this->query('Authenticate', [$_ENV['admin_login'], $_ENV['admin_password']]);
    }

    public function query(string $method, array $params = []): Container
    {
        $xmlString = $this->request->createRpcRequest($method, $params);

        if (strlen($xmlString) > self::MAX_REQ_SIZE) {
            throw new Exception("Transport error - request too large {$method}");
        }

        if (!$this->sendRequest($xmlString)) {
            throw new Exception("Transport error - connection interrupted");
        }

        $result = $this->result($method);

        if ($result->has('#err')) {
            Aseco::console("{$method} error {$result->get('#err.faultString')}{$result->get('#err.faultCode')}");
            throw new Exception("{$method} error {$result->get('#err.faultString')}{$result->get('#err.faultCode')}");
        }

        return $result;
    }

    #[Deprecated('kept for refrence')]
    public function addCall(string $methodName, array $args): int
    {
        $this->methodName = $methodName;
        $this->calls = [
            'params' => $args
        ];

        return count($this->calls) - 1;
    }

    public function readCallBack(float $timeout = 2.0): bool
    {
        $somethingReceived = !empty($this->cb_message);

        $this->socket->setTimeout($timeout);

        $read = [$this->socket->socket];
        $write = null;
        $expect = null;

        $timeoutSeconds = (int)$timeout;
        $timeoutUsec = (int)(($timeout - $timeoutSeconds) * 1_000_000);

        $available = @stream_select(
            $read,
            $write,
            $expect,
            $timeoutSeconds,
            $timeoutUsec
        );

        while ($available !== false && $available > 0) {
            $size = 0;
            $recvHandle = 0;

            $contents = $this->readContents(8);
            if (strlen($contents) > 0) {
                $data = unpack('Vsize/Vhandle', $contents);
                $size = $data['size'];
                $recvHandle = $this->convertHandle($data['handle']);
            }

            if ($recvHandle == 0 || $size == 0) {
                throw new Exception("transport error - connection interrupted");
            }

            if ($size > self::MAX_RES_SIZE) {
                throw new Exception("transport error - response too large");
            }

            $contents = $this->readContents($size);

            if (strlen($contents) < $size) {
                throw new Exception("transport error - failed to read full response");
            }

            if (($recvHandle & 0x80000000) == 0) {
                $this->cb_message[] = $this->response->parseMethodCall($contents);
            }

            $read = [$this->socket->socket];
            $available = @stream_select($read, $write, $except, 0, 0);
        }

        return $somethingReceived;
    }

    public function popCBResponse(): ?Container
    {
        return array_shift($this->cb_message) ?: null;
    }

    public function terminate(): void
    {
        $this->socket->close();
    }

    protected function sendRequest(string $xml): bool
    {
        if (!is_resource($this->socket->socket)) {
            return false;
        }

        $this->socket->setTimeout(self::TIMEOUT);
        $this->reqHandle++;

        $bytes = pack('VVA*', strlen($xml), $this->reqHandle, $xml);

        return $this->socket->write($bytes) !== 0;
    }

    protected function result(string $method): Container
    {
        $contents = '';

        if (is_resource($this->socket->socket)) {
            do {
                $size = 0;
                $recvHandle = 0;
                $this->socket->setTimeout(self::TIMEOUT);
                $contents = $this->socket->read(8);

                if ($contents === false) {
                    throw new Exception('Transport error - cannot read socket');
                }
                if (strlen($contents) === 0) {
                    throw new Exception('Transport error - cannot read size');
                }

                $result = unpack('Vsize/Vhandle', $contents);
                $size = $result['size'];
                $recvHandle = $this->convertHandle($result['handle']);

                if ($recvHandle === 0 || $size === 0) {
                    throw new Exception('Transport error - connection interrupted');
                }

                if ($size > self::MAX_RES_SIZE) {
                    throw new Exception("Transport error - response too large {$method}");
                }

                $contents = $this->readContents($size);
                if (($recvHandle & 0x80000000) == 0) {
                    $this->cb_message[] = $this->response->parseMethodCall($contents);
                }
            } while ($recvHandle !== $this->reqHandle);

            return $this->response->parseResponse($method, $contents);
        }

        return Container::fromArray([], true);
    }

    protected function convertHandle(int $handle): int
    {
        $bits = sprintf('%b', $handle);
        return (strlen($bits) === 64) ? (int)bindec(substr($bits, 32)) : $handle;
    }

    protected function readContents(int $size): string
    {
        $contents = '';

        if (is_resource($this->socket->socket)) {
            $this->socket->setTimeout(0.10);
            while (strlen($contents) < $size) {
                $chunk = $this->socket->read($size - strlen($contents));
                if ($chunk === '') {
                    throw new Exception('Transport error - reading contents');
                }
                $contents .= $chunk;
            }
        }

        return $contents;
    }

    protected function init(): void
    {
        $handshake = '';

        if (is_resource($this->socket->socket)) {
            $header = $this->socket->read(4);

            if ($header === false || strlen($header) !== 4) {
                throw new Exception('Transport error - failed to read protocol header');
            }

            $unpacked = @unpack('Vsize', $header);
            if ($unpacked === false || !isset($unpacked['size'])) {
                throw new Exception('Transport error - failed to unpack protocol header');
            }

            $size = $unpacked['size'];

            if ($size > 64) {
                throw new Exception('Transport error - wrong low-level protocol header');
            }

            $handshake = $this->socket->read($size);
            if ($handshake === false || strlen($handshake) !== $size) {
                throw new Exception('Transport error - failed to read handshake');
            }

            if (trim($handshake) !== 'GBXRemote 2') {
                throw new Exception("Unsupported protocol: '{$handshake}'. Only GBXRemote 2 is supported.");
            }
        }
    }

    protected function rpcMessages(): array
    {
        return Aseco::safeJsonDecode(Aseco::safeFileGetContents(Aseco::jsonFolderPath() . 'methods.json'))['methods'];
    }
}
