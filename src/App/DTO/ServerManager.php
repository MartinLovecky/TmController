<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\DTO;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Services\Server;
use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Plugins\Manager\PluginManager;

class ServerManager
{
    private bool $connected = false;

    public function __construct(
        private Client $client,
        private Server $server,
        private PlayerService $playerService,
        private PluginManager $pm,
        private TmContainer $config
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
