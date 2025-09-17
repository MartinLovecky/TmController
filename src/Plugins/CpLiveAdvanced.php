<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Log, Sender};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class CpLiveAdvanced
{
    /**
     *
     * @var string $file cpLive/
     */
    private string $file = '';
    private int $numberCps = 0;
    private float $lastUpdate = 0.0;
    private bool $needsUpdate = true;
    private const int MAX_DISPLAY_ROWS = 12;
    private const int ANSWER_TOGGLE_HUD = 1928390;
    private const int ANSWER_SWITCH_COLOR = 1928396;
    private const int NICK_CHANGE = 0;

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

    public function onEverySecond(): void
    {
        if ($this->needsUpdate) {
            $this->needsUpdate = false;
            $this->showListToAll();
        }
    }

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

        // Modern approach: always queue the update.
        // showListToAll() is throttled internally, so no spam.
        $this->updateList();

        $this->bindToggleKey($player->get('Login'));
    }

    public function onPlayerLink(TmContainer $manialink): void
    {
        $message = $manialink->get('message');
        $login = $manialink->get('login');
        $player = $this->playerService->getPlayerByLogin($login);

        if ($message === self::ANSWER_TOGGLE_HUD) {
            $player->set('CPL.Manialink', $player->has('CPL.Manialink'));
            $this->showListToLogin($login);
        } elseif ($message === self::ANSWER_SWITCH_COLOR) {
            $this->changeNickDisplay($player);
        }
    }

    public function onPlayerCheckpoint(TmContainer $call): void
    {
        Log::debug('CpLiveAdvanced: onPlayerCheckpoint', $call->toArray(), 'CpLiveAdvanced');
        dd('onPlayerCheckpoint', $call->toArray());
        $login = $call->get('Login');
        $cpNumber = $call->get('CurrentCheckPoint');
        $time = $call->get('CurrentRaceTime');
        $player = $this->playerService->getPlayerByLogin($login);

        if ($player instanceof TmContainer) {
            $player->set('CPL.CPNumber', $cpNumber)->set('CPL.Time', $time);
            $this->updateList($login);
        }
    }

    public function onPlayerInfoChanged(TmContainer $call): void
    {
        Log::debug('CpLiveAdvanced: onPlayerInfoChanged', $call->toArray(), 'CpLiveAdvanced');
        dd('onPlayerInfoChanged', $call->toArray());
        $login = $call->get('Login');
        $nickName = htmlspecialchars(string: $call->get('NickName'), encoding: 'UTF-8');
        $player = $this->playerService->getPlayerByLogin($login);

        if ($player instanceof TmContainer) {
            $player->set('CPL.White', preg_replace('/\$[0-9A-F]{3}/i', '', $nickName))
                ->set('CPL.Colored', $nickName);
            $this->updateList($login);
        }
    }

    public function onPlayerFinish(TmContainer $call): void
    {
        Log::debug('CpLiveAdvanced: onPlayerFinish', $call->toArray(), 'CpLiveAdvanced');
        dd('onPlayerFinish', $call->toArray());
        $login = $call->get('Login');
        $player = $this->playerService->getPlayerByLogin($login);

        if ($player instanceof TmContainer) {
            $player->set('CPL.CPNumber', $this->numberCps)->set('CPL.Time', $call->get('BestRaceTime'));
            $this->updateList($login);
        }
    }

    public function onPlayerDisconnect(TmContainer $call): void
    {
        Log::debug('CpLiveAdvanced: onPlayerDisconnect', $call->toArray(), 'CpLiveAdvanced');
        dd('onPlayerDisconnect', $call->toArray());
        $login = $call->get('Login');
        $this->hideList($login);
        $this->updateList();
    }

    public function onEndRound(): void
    {
        $this->emptyManialinks();
    }

    public function onBeginRound(): void
    {
        $this->playerService->eachPlayer(function (TmContainer $player) {
            $player->set('CPL.CPNumber', 0)->set('CPL.Time', 0)->set('CPL.Spectator', $player->get('IsSpectator'));
            $this->showListToLogin($player->get('Login'));
        });

        $this->getTrackInfo();
        $this->updateList();
    }

    public function onRestartChallenge(): void
    {
        $this->emptyManialinks();
    }

    public function handleChatCommand(TmContainer $player): void
    {
        $comandName = $player->get('command.name');

        if ($comandName !== 'cpl') {
            return;
        }

        $params = $player->get('command.params');
        $login = $player->get('Login');

        match ($params) {
            'color' => $this->changeNickDisplay($player),
            'toggle' => $this->showListToLogin($login),
            'help' => $this->sendHelp($login),
            default => $this->sendHelp($login)
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

    private function changeNickDisplay(TmContainer $player): void
    {
        $lastNickUpdate = $player->get('CPL.LastNickUpdate', default: self::NICK_CHANGE);
        $login = $player->get('Login');

        if (time() - $lastNickUpdate >= self::NICK_CHANGE) {
            $whiteNick = $player->get('CPL.WhiteNick');
            $player->set('CPL.WhiteNick', !$whiteNick)->set('CPL.LastNickUpdate', time());
            $this->showListToLogin($login);
        } else {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: "{#error{Please wait {1}second(s) beforechanging nickname color again.",
                formatArgs: [self::NICK_CHANGE]
            );
        }
    }

    private function updateList(?string $login = null): void
    {
        $activePlayers = $this->playerService->getActivePlayers();

        if (!empty($activePlayers)) {
            // Spaceship operator
            usort($activePlayers, fn($a, $b) => $b->get('CPL.CPNumber') <=> $a->get('CPL.CPNumber'));
        }

        if ($login) {
            $this->showListToLogin($login);
        } else {
            $this->showListToAll();
        }
    }

    private function sendHelp(string $login): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: "{info}/cpl color {#default}- Toggle nickname color between colored and white. \n" .
            "{#info}/cpl toggle {#default}- Show the checkpoint list. \n" .
            "{#info}/cpl help {#default}- Show this help message.",
            formatMode: Sender::FORMAT_COLORS
        );
    }

    private function showListToAll(): void
    {
        $time = microtime(true) * 1000;
        // prevent too frequent updates
        if ($time - $this->lastUpdate < 400) {
            $this->needsUpdate = true;
            return;
        }

        $this->playerService->eachPlayer(function (TmContainer $player) {
            if ($player->has('CPL.Manialink')) {
                $this->showList($player->get('Login'));
            }
        });

        $this->lastUpdate = $time;
    }

    private function showListToLogin(string $login): void
    {
        $player = $this->playerService->getPlayerByLogin($login);

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
                "list" => $this->playerService->getActivePlayers(),
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

    private function emptyManialinks(): void
    {
        $this->sender->sendRenderToAll(
            template: "{$this->file}empty",
            formatMode: Sender::FORMAT_NONE,
            timeout: 1
        );
    }

    private function getTrackInfo(): void
    {
        $challenge = $this->challengeService->getChallenge();
        $this->numberCps = $challenge->get('NbCheckpoints') - 1;
    }

    private function compareCpNumbers(TmContainer $a, TmContainer $b): int
    {
        $cpA = $a->get('CPL.CPNumber');
        $cpB = $b->get('CPL.CPNumber');

        if ($cpA === $cpB) {
            return 0;
        }

        // Higher CPNumber comes first
        return ($cpA > $cpB) ? -1 : 1;
    }
}
