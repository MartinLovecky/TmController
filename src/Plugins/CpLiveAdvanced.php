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
        $nickName = htmlspecialchars($player->get('NickName'), ENT_QUOTES, 'UTF-8');
        $player->setMultiple([
            'CPL.White' => preg_replace('/\$[0-9A-F]{3}/i', '', $nickName),
            'CPL.Colored' => $nickName,
            'CPL.Manialink' => true,
            'CPL.WhiteNick' => false,
            'CPL.LastNickUpdate' => 0,
            'CPL.CPNumber' => 0,
            'CPL.Time' => 0,
            'CPL.Spectator' => $player->get('IsSpectator'),
        ]);

        // throttled internally, so no spam.
        $this->updateList();
        $this->bindToggleKey($player->get('Login'));
    }

    public function onPlayerLink(TmContainer $manialink): void
    {
        $player = $this->playerService->getPlayerByLogin($manialink->get('login'));

        if (!$player instanceof TmContainer) {
            return;
        }

        $message = $manialink->get('message');

        match ($message) {
            self::ANSWER_TOGGLE_HUD => $this->toggleHud($player),
            self::ANSWER_SWITCH_COLOR => $this->changeNickDisplay($player),
            default => null,
        };
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
        $this->playerService->eachPlayer(fn(TmContainer $player) => $player->setMultiple([
            'CPL.CPNumber' => 0,
            'CPL.Time' => 0,
            'CPL.Spectator' => $player->get('IsSpectator'),
        ]));

        $this->getTrackInfo();
        $this->updateList();
    }

    public function onRestartChallenge(): void
    {
        $this->emptyManialinks();
    }

    public function handleChatCommand(TmContainer $player): void
    {
        if ($player->get('command.name') !== 'cpl') {
            return;
        }

        $login = $player->get('Login');

        match ($player->get('command.params')) {
            'color' => $this->changeNickDisplay($player),
            'toggle' => $this->showListToLogin($login),
            default => $this->sendHelp($login)
        };
    }

    private function toggleHud(TmContainer $player): void
    {
        $player->set('CPL.Manialink', !$player->has('CPL.Manialink'));
        $this->showListToLogin($player->get('Login'));
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
            $player->set('CPL.WhiteNick', !$player->get('CPL.WhiteNick'))->set('CPL.LastNickUpdate', time());
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

        $login ? $this->showListToLogin($login) : $this->showListToAll();
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

        $this->playerService->eachPlayer(fn(TmContainer $player) =>
            $player->has('CPL.Manialink') && $this->showList($player->get('Login')));
        $this->lastUpdate = $time;
    }

    private function showListToLogin(string $login): void
    {
        $player = $this->playerService->getPlayerByLogin($login);

        $player instanceof TmContainer ? $this->showList($login) : $this->hideList($login);
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

    private function displayTitleBar(string $login): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->file}titleBar",
            context: ['numberCPs' => $this->numberCps],
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
}
