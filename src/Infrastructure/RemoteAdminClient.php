<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure;

use Exception;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Infrastructure\Gbx\Client;

class RemoteAdminClient
{
    private TmContainer $virtualAdmin;

    private function __construct(private Client $client)
    {
        // Authenticate with dedicated service account
        $this->client->query('Authenticate', [$_ENV['admin_login'], $_ENV['admin_password']]);
    }

    public function executeCommand(string $method, array $params = []): TmContainer
    {
        // $allowed = ['ChatSendServerMessage', 'Kick', 'Ban', 'Restart'];
        // if (!in_array($method, $allowed, true)) {
        //     throw new Exception("Method {$method} not allowed");
        // }
        return $this->client->query($method, $params);
    }

    public function createVirtualAdmin(string $login): TmContainer
    {
        $this->virtualAdmin = new TmContainer([
            'Login' => $login,
            'NickName' => $login,
            'isMasterAdmin' => true,
            'created' => time()
        ]);
        return $this->virtualAdmin;
    }

    public function getVirtualAdmin(): TmContainer
    {
        return $this->virtualAdmin;
    }
}
