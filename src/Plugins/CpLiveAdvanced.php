<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Sender;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\PlayerService;

class CpLiveAdvanced
{
    /**
     *
     * @var string $file cpLive/
     */
    private string $file = '';
    private int $numberCps = 0;
    private float $lastUpdate = 0.0;
    private const int MAX_DISPLAY_ROWS = 12;
    private const int ANSWER_TOGGLE_HUD = 1928390;
    private const int ANSWER_SWITCH_COLOR = 1928396;
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
            ->set('CPL.White', preg_replace('/\$[0-9A-F]{3}/i', '', $nickName))
            ->set('CPL.Colored', $nickName)
            ->set('CPL.Manialink', true)
            ->set('CPL.WhiteNick', false)
            ->set('CPL.LastNickUpdate', 0)
            ->set('CPL.CPNumber', 0)
            ->set('CPL.Time', 0)
            ->set('CPL.Spectator', $spectator);

        if (!empty($this->list) && count($this->list) >= self::MAX_DISPLAY_ROWS) {
            $this->updateList($player->get('Login'));
        } else {
            $this->updateList();
        }

        $this->bindToggleKey($player->get('Login'));
    }

    public function onPlayerLink(TmContainer $manialink): void
    {
        $message = $manialink->get('message');
        $login = $manialink->get('login');

        if ($message === self::ANSWER_TOGGLE_HUD) {
            $player = $this->list[$login];
            $player->has('CPL');
        } elseif ($message === self::ANSWER_SWITCH_COLOR) {
        }
    }

    public function handleChatCommand(TmContainer $player): void
    {
        $comandName = $player->get('command.name');
        $params = $player->get('command.params');
        $arg = $player->get('command.arg');

        match ($comandName) {
            'color' => null,
            'toggle' => $this->showListToLogin($player->get('Login')),
            default => null
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

    private function updateList(?string $login = null): void
    {
        $this->refillList();

        if (!empty($this->list)) {
            usort($this->list, [$this, "compareCpNumbers"]);
        }

        if (!isset($login)) {
            $this->showListToAll();
        } else {
            $this->showListToLogin($login);
        }
    }

    private function showListToAll()
    {
    }

    private function showListToLogin(string $login)
    {
        $player = $this->list[$login] ?? null;

        if ($player instanceof TmContainer) {
            $this->showList($login);
        } else {
            $this->hideList($login);
        }
    }

    private function displayTitleBar(string $login): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->file}titleBar",
            context: ['numberCPs' => $this->numberCps],
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function showList(string $login): void
    {
        $this->displayTitleBar($login);

        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->file}showList",
            context: [
                "whiteNickAllowed" => true,
                "list" => $this->list,
                'numberCPs' => $this->numberCps
            ],
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function hideList(string $login): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->file}hideList",
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function compareCpNumbers(TmContainer $a, TmContainer $b): int
    {
        $cpA = $a->get('CPL.CPNumber');
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
