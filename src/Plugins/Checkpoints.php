<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Log;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Repository\{ChallengeService, RepositoryManager};

class Checkpoints
{
    public array $checkpoints = [];
    private array $bestCps = [];
    private array $currCps = [];
    private array $speccers = [];

    public function __construct(
        private ChallengeService $challengeService,
        private RepositoryManager $repositoryManager,
    ) {
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        $login = $player->get('Login');
        $this->checkpoints[$login] = [
            'checkpoints' => [],
            'currFin'     => PHP_INT_MAX,
            'bestFin'     => PHP_INT_MAX,
            'loclRec'     => -1,
            'dediRec'     => -1,
            'best_time'   => 0,
        ];
        if ($this->challengeService->getGameMode() === 'laps') {
            $this->checkpoints[$login]['currFin'] = 0;
        }

        $cps = $this->repositoryManager->fetch(
            Table::PLAYERS_EXTRA,
            'playerID',
            $login,
            ['cps', 'dedicps']
        );

        if (is_array($cps)) {
            $this->checkpoints[$login]['loclrec'] = array_key_first($cps);
            $this->checkpoints[$login]['dedirec'] = [...$cps];
        } else {
            $this->checkpoints[$login]['loclrec'] = 0;
            $this->checkpoints[$login]['dedirec'] = 0;
        }
    }

    public function onPlayerDisconnect(string $login): void
    {
        $update = [
            'cps' => $this->checkpoints[$login]['loclrec'],
            'dedicps' => $this->checkpoints[$login]['dedirec'][0],
        ];
        Log::debug('Checkpoints', $update, 'onPlayerDisconnect');
        $this->repositoryManager->update(Table::PLAYERS_EXTRA, $update, $login);

        unset($this->checkpoints[$login]);
    }

    public function onNewChallenge()
    {
        foreach ($this->checkpoints as $login => $_) {
            $this->checkpoints[$login]['bestCps'] = $this->bestCps;
            $this->checkpoints[$login]['currCps'] = $this->currCps;
            $this->checkpoints[$login]['bestFin'] = PHP_INT_MAX;

            if ($this->challengeService->getGameMode() === 'laps') {
                $this->checkpoints[$login]['currFin'] = 0;
            } else {
                $this->checkpoints[$login]['currFin'] = PHP_INT_MAX;
            }
        }
    }

    public function onBeginRound()
    {
    }

    public function onEndRace()
    {
    }

    public function onRestartChallenge()
    {
    }

    public function onCheckpoint()
    {
    }

    public function onPlayerFinish1()
    {
    }

    public function onPlayerInfoChanged()
    {
    }
}
