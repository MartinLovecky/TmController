<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Sender;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\PlayerService;

class CpLiveAdvanced
{
    private string $file = '';
    private int $numberCps = 0;
    private float $lastUpdate = 0.0;
    private const int MAX_DISPLAY_ROWS = 12;
    private array $list = [];

    public function __construct(
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private Sender $sender,
    ) {
        $this->file = 'cpLive' . DIRECTORY_SEPARATOR;
    }

    public function onSync(): void
    {
        $this->numberCps = $this->challengeService->getGBX()->nbChecks - 1;
        $this->lastUpdate = microtime(true) * 1000;
    }

    // ???????
    public function onPlayerConnect(TmContainer $player): void
    {
        $spectator = $player->get('IsSpectator');
        $nickName = htmlspecialchars(string: $player->get('NickName'), encoding: 'UTF-8');

        $player
            ->set('NickName.White', preg_replace('/\$[0-9A-F]{3}/i', '', $nickName))
            ->set('NickName.Colored', $nickName)
            ->set('NickName.Manialink', true)
            ->set('NickName.WhiteNick', false)
            ->set('NickName.Manialink', true)
            ->set('NickName.LastNickUpdate', 0)
            ->set('NickName.CPNumber', 0)
            ->set('NickName.Time', 0)
            ->set('NickName.Spectator', $spectator);

        if (!empty($this->list) && count($this->list) >= self::MAX_DISPLAY_ROWS) {
            $this->updateList($player->get('Login'));
        } else {
            $this->updateList();
        }

        $this->bindToggleKey($player->get('Login'));
    }

    public function handleChatCommand(TmContainer $player): void
    {
        $comandName = $player->get('command.name');
        $params = $player->get('command.params');

        match ($comandName) {
            'color' => '',
            'toggle' => '',
            default => ''
        };
    }

    private function bindToggleKey(string $login): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->file}toggleKey",
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function updateList(?string $login = null)
    {
        $this->refillList();

        if (!empty($this->list)) {
            usort($this->list, [$this, "compareCpNumbers"]);
        }
    }

    private function showListToAll()
    {
    }

    private function compareCpNumbers(TmContainer $a, TmContainer $b): int
    {
        $cpA = $a->get('NickName.CPNumber');
        $cpB = $b->get('NickName.CPNumber');

        if ($cpA === $cpB) {
            return 0;
        }

        // Higher CPNumber comes first
        return ($cpA > $cpB) ? -1 : 1;
    }


    private function refillList(): void
    {
        $this->playerService->eachPlayer(function (TmContainer $player) {
            if (!$player->get('IsSpectator')) {
                $this->list[$player->get('Login')] = $player;
            }
        });
    }
}
