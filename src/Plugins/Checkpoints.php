<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\RepositoryManager;

class Checkpoints
{
    public int $bestFin = 9223372036854775807;
    public int $currFin = 9223372036854775807;
    private int $loclRec = -1;
    private int $dediRec = -1;
    private int $best_time = 0;
    public array $checkpoints = [];
    private array $bestCps = [];
    private array $currCps = [];
    private array $speccers = [];

    public function __construct(
        protected ChallengeService $challengeService,
        protected RepositoryManager $repositoryManager,
    ) {
    }

    public function onPlayerConnect(Container $player)
    {
        $login = $player->get('Login');
        $this->checkpoints[$login] = $this;
        if ($this->challengeService->getGameMode() === 'laps') {
            $this->checkpoints[$login]->currFin = 0;
        }
        $cps = $this->repositoryManager->get(
            Table::PLAYERS_EXTRA,
            'playerID',
            $login,
            ['dedicps', 'cps']
        );
        dd($cps);

        //$this->chekpoints[$login]->loclrec = 0;

        //$this->chekpoints[$login]->loclrec = $cps['cps'];
        //$this->chekpoints[$login]->dedicps = $cps['dedicps'];
    }

    public function onPlayerDisconnect(Container $player)
    {
    }

    public function onNewChallenge(Container $challenge)
    {
        foreach ($this->checkpoints as $login => $_) {
            $this->checkpoints[$login]->bestCps = $this->bestCps;
            $this->checkpoints[$login]->currCps = $this->currCps;
            $this->checkpoints[$login]->bestFin = $this->bestFin;

            if ($this->challengeService->getGameMode() === 'laps') {
                $this->checkpoints[$login]->currFin = 0;
            } else {
                $this->checkpoints[$login]->currFin = $this->currFin;
            }

            //TODO complete RecordService
        }
    }

    public function onRestartChallenge()
    {
        // array $checkpoints
        // loop  checkpoints and set //curr_cps = []
        // curr_fin = $this->currFin
    }
}
