<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

class Socket
{
    private string $host = '127.0.0.1';
    private int $port = 5009;
    private float $timeout = 60.0;
    private int $errCode = 0;
    private string $errMessage = '';
    /**
     *
     * @var resource|false $socket
     */
    public $socket;

    public function __construct()
    {
        $socket = stream_socket_client(
            "tcp://{$this->host}:{$this->port}",
            $this->errCode,
            $this->errMessage,
            $this->timeout
        );

        $this->socket = $socket;
    }

    public function setTimeout(float $timeout): void
    {
        $sec = (int) $timeout;
        $microsec = (int) round(($timeout - $sec) * 1000000);

        stream_set_timeout($this->socket, $sec, $microsec);
    }

    public function write(string $data): false|int
    {
        if (!is_resource($this->socket)) {
            return false;
        }

        $writtenBytes = 0;
        $totalBytes = strlen($data);

        while ($writtenBytes < $totalBytes) {
            $result = fwrite($this->socket, substr($data, $writtenBytes));
            $writtenBytes += $result;
        }

        return $writtenBytes;
    }

    public function gets(int $len = 1024): false|string
    {
        return !is_resource($this->socket) ? false : fgets($this->socket, $len);
    }

    public function read(int $len = 1024): false|string
    {
        return !is_resource($this->socket) ? false : fread($this->socket, $len);
    }

    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = false;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
