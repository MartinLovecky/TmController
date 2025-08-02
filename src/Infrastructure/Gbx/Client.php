<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Gbx;

use Exception;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Socket;
use Yuhzel\TmController\Infrastructure\Xml\{Parser, Request, Response};

class Client
{
    private const int MAX_REQ_SIZE = 512 * 1024 - 8;
    private const int MAX_RES_SIZE = 4096 * 1024;
    private const float TIMEOUT = 20.0;
    private int $reqHandle = 0x80000000;
    private int $protocol = 0;
    /** @var Container[] */
    private array $cb_message = [];
    public string $methodName = '';
    public array $calls = [];

    public function __construct(
        protected Socket $socket,
        protected Request $request,
        protected Response $response
    ) {
        $this->init();
        $this->query('Authenticate', [$_ENV['admin_login'], $_ENV['admin_password']]);
    }

    public function query(string $method, array $params = [], bool $readonly = true): Container
    {
        $xmlString = $this->request->createRpcRequest($method, $params);

        if (strlen($xmlString) > self::MAX_REQ_SIZE) {
            throw new Exception("Transport error - request too large {$method}");
        }

        if (!$this->sendRequest($xmlString)) {
            throw new Exception("Transport error - connection interrupted");
        }

        $result = $this->result($method, $readonly);

        if ($result->has('#err')) {
            Aseco::console("{$method} error {$result->get('#err.faultString')}{$result->get('#err.faultCode')}");
            throw new Exception("{$method} error {$result->get('#err.faultString')}{$result->get('#err.faultCode')}");
        }

        return $result;
    }

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
        $something_received = count($this->cb_message) > 0;
        $contents = '';

        $this->socket->setTimeout($timeout);

        $read = [$this->socket->socket];
        $write = null;
        $expect = null;
        $timeoutSeconds = (int) $timeout;
        $timeoutMicroseconds = (int)(($timeout - $timeoutSeconds) * 1_000_000);
        $nb = @stream_select(
            $read,
            $write,
            $expect,
            $timeoutSeconds,
            $timeoutMicroseconds
        );

        while ($nb !== false &&  $nb > 0) {
            $timeout = 0;
            $size = 0;
            $recvHandle = 0;
            $contents = $this->readContents(8);

            if ($this->protocol === 1) {
                $contents = $this->readContents(4);
                if (strlen($contents) > 0) {
                    $size = $this->bigEdianUnpack('Vsize', $contents)['size'];
                    $recvHandle = $this->reqHandle;
                }
            } elseif ($this->protocol === 2) {
                $contents =  $this->readContents(8);
                if (strlen($contents) > 0) {
                    $result = unpack('Vsize/Vhandle', $contents);
                    $size = $result['size'];
                    $recvHandle = $this->convertHandle($result['handle']);
                }
            }

            if ($recvHandle == 0 || $size == 0) {
                throw new Exception("transport error - connection interrupted");
            }

            if ($size > 4096 * 1024) {
                throw new Exception("transport error - response too large");
            }

            $contents = $this->readContents($size);

            if (!$contents || strlen($contents) < $size) {
                throw new Exception("transport error - failed to read full response");
            }

            if (($recvHandle & 0x80000000) == 0) {
                $this->cb_message[] = $this->response->parseMethodCall($contents);
            }

            $read = [$this->socket->socket];
            $write = null;
            $except = null;
            $nb = @stream_select($read, $write, $except, 0, $timeout);
            if ($nb !== false) {
                $nb = count($read);
            }
        }

        return $something_received;
    }

    public function getCBResponses(): array
    {
        return $this->cb_message;
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

        $bytes = $this->protocol === 2
        ? pack('VVA*', strlen($xml), $this->reqHandle, $xml)
        : pack('Va*', strlen($xml), $xml);

        return $this->socket->write($bytes) !== 0;
    }

    protected function result(string $method, bool $readonly): Container
    {
        $contents = '';

        if (is_resource($this->socket->socket)) {
            do {
                $size = 0;
                $recvHandle = 0;
                $this->socket->setTimeout(self::TIMEOUT);

                if ($this->protocol === 1) {
                    $contents = $this->socket->read(4);
                    if (strlen($contents) === 0) {
                        throw new Exception('Transport error - cannot read size');
                    }
                    $size = $this->bigEdianUnpack('Vsize', $contents)['size'];
                    $recvHandle = $this->reqHandle;
                } elseif ($this->protocol === 2) {
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
                }

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

            return $this->response->parseResponse($method, $contents, $readonly);
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

    protected function setProtocolVersion(): void
    {
        $littleEdian = pack('V', 1);
        $nativeEdian = unpack('L', $littleEdian)[1];
        $this->protocol = ($nativeEdian === 1) ? 2 : 1;
    }

    protected function init(): void
    {
        $handshake = '';

        if (is_resource($this->socket->socket)) {
            $this->setProtocolVersion();

            $header = $this->socket->read(4);

            $size = $this->protocol === 2
            ? unpack('Vsize', $header)['size']
            : $this->bigEdianUnpack('Vsize', $header)['size'];

            if ($size > 64) {
                throw new Exception('Transport error - wrong low-level protocol header');
            }

            $handshake = $this->socket->read($size);
        }
        $handshake === 'GBXRemote 1' ? $this->protocol = 1 : $this->protocol = 2;
    }

    protected function bigEdianUnpack(string $format, string $data): array
    {
        $ar = unpack($format, $data);
        $vals = array_values($ar);
        $formats = explode('/', $format);
        $i = 0;

        foreach ($formats as $formatPart) {
            $repeater = (int) substr($formatPart, 1) ?: 1;
            if (isset($formatPart[1]) && $formatPart[1] === '*') {
                $repeater = count($ar) - $i;
            }
            if ($formatPart[0] !== 'd') {
                $i += $repeater;
                continue;
            }
            for ($a = $i; $a < $i + $repeater; ++$a) {
                $p = strrev(pack('d', $vals[$i]));
                $vals[$i] = unpack('d1d', $p)['d'];
                ++$i;
            }
        }

        return array_combine(array_keys($ar), array_values($vals));
    }

    protected function rpcMessages(): array
    {
        return Aseco::safeJsonDecode(Aseco::safeFileGetContents(Aseco::jsonFolderPath() . 'methods.json'))['methods'];
    }
}
