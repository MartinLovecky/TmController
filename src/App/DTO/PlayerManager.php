<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\DTO;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Core\TmContainer;

class PlayerManager
{
    private array $bannedIps = [];
    private array $players = [];

    public function connect(TmContainer $call): void
    {
        $player = $call->player ?? null;
        if (!$player) {
            return;
        }

        if (in_array($player->get('IPAddress'), $this->bannedIps)) {
            Aseco::consoleText("Banned player attempted to connect: " . $player->get('NickName'));
            return;
        }

        $this->players[$player->get('Login')] = $player;
        Aseco::consoleText("Player connected: " . $player->get('NickName'));
    }

    public function disconnect(TmContainer $call): void
    {
        $player = $call->player ?? null;
        if (!$player) {
            return;
        }

        unset($this->players[$player->get('Login')]);
        Aseco::consoleText("Player disconnected: " . $player->get('NickName'));
    }

    public function banIp(string $ip, string $reason = ''): void
    {
        $this->bannedIps[] = $ip;
        Aseco::consoleText("IP banned: $ip Reason: $reason");
    }

    public function getPlayerList(): array
    {
        return $this->players;
    }
}
