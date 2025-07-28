<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\{Fluent, Table};

class Checkpoints
{
    public array $checkpoints = [];
    public int $bestFin = 9223372036854775807;
    public int $currFin = 9223372036854775807;

    public function __construct(protected Fluent $fluent)
    {
    }

    public function onPlayerConnect(Container $player)
    {
        // $this->checkpoits->set("{$login}.loclrec", 0);
        $login = $player->get('Login');
        //$this->checkpoints[$login];
        $cps = $this->fluent->query
            ->from(Table::PLAYERS_EXTRA->value)
            ->select(['dedicps', 'cps'])
            ->where('playerID', $login)
            ->fetch();
        if (!$cps) {
            //$this->chekpoints[$login]->loclrec = 0;
            return;
        } else {
            //$this->chekpoints[$login]->loclrec = $cps['cps'];
            //$this->chekpoints[$login]->dedicps = $cps['dedicps'];
        }
    }

    public function onRestartChallenge()
    {
        // array $checkpoints
        // loop  checkpoints and set //curr_cps = []
        // curr_fin = $this->currFin
    }

    public function onNewChallenge()
    {
        // same as onRestartChallenge
    }
}
