<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Command;

class Rasp
{
    private array $commands = [];

    public function __construct()
    {
        $this->commands = [
            ['rank', [$this, 'rank'], 'Shows your current server rank'],
            ['top10', [$this, 'top10'], 'Displays top 10 best ranked players'],
            ['top100', [$this, 'top100'], 'Displays top 100 best ranked players'],
            ['topwins', [$this, 'topwins'], 'Displays top 100 victorious players'],
            ['active', [$this, 'active'], 'Displays top 100 most active players']
        ];
        Command::register($this->commands, 'Rasp');
    }
}
