<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\App\Service\Sender;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Manager\PageListManager;
use Yuhzel\TmController\Repository\ChallengeService;

class Cpll
{
    private int $cpllTrackCps = 0;
    private array $cpll = [];

    public function __construct(
        private ChallengeService $challengeService,
        private PageListManager $pageListManager,
        private Sender $sender
    ) {
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: Aseco::getChatMessage('cpll_info', 'rasp'),
            formatMode: Sender::FORMAT_COLORS
        );
    }

    public function onPlayerDisconnect(?string $login = null): void
    {
        if ($login !== null && isset($this->cpll[$login])) {
            unset($this->cpll[$login]);
        }
    }

    public function onPlayerFinish(mixed ...$args): void
    {
        $login = $args[1] ?? null;
        $this->onPlayerDisconnect($login);
    }

    public function onNewChallenge(): void
    {
        $this->cpllTrackCps = $this->challengeService->getChallenge()->get('NbChecks') - 1;
        $this->cpll = [];
    }

    public function onRestartChallenge(): void
    {
        $this->cpll = [];
    }

    public function onCheckpoint(TmContainer $call): void
    {
        $this->cpll[$call->get('login')] = [
            'time' => $call->get('time', 0),
            'cp'   => $call->get('checkpointIndex') + 1
        ];
    }

    //TODO move admin cmd to ChatAdmin
    public function handleChatCommand(TmContainer $player)
    {
        if ($player->get('command.name') !== 'cpll') {
            return;
        }

        match ($player->get('command.param')) {
            'cp' => $this->listCps($player),
            'mycp' => null,
            'on', 'off', 'filter' => null, // THESE SHOULD BE HANDLED BY ChatAdmin::CLASS
            default => null
        };
    }

    private function listCps(TmContainer $player)
    {
        if ($player->get('isSpectator')) {
            unset($this->cpll[$player->get('Login')]);
        }

        if (!empty($this->cpll)) {
            //TODO: Continue
        }
    }
}
