<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Tm;

use Yuhzel\TmController\App\Aseco;

class ServerManager
{
    private bool $connected = false;

    public function __construct(
        private Client $client,
        private Server $server,
        private PlayerService $playerService,
        private PluginManager $pm,
        private Container $config
    ) {
    }

    public function connect(): void
    {
        Aseco::consoleText("Connecting to the server...");
        $this->connected = true;
        Aseco::consoleText("Connected.");
    }

    public function disconnect(): void
    {
        if (!$this->connected) {
            return;
        }

        Aseco::consoleText("Disconnecting from the server...");
        $this->connected = false;
        Aseco::consoleText("Disconnected.");
    }

    public function sync(): void
    {
        if (!$this->connected) {
            return;
        }

        Aseco::consoleText("Synchronizing server state...");
    }

    public function broadcast(string $message): void
    {
        if (!$this->connected) {
            return;
        }

        Aseco::consoleText("[Broadcast] $message");
    }
}
