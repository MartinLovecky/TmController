<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Tm;

use League\Container\Container;
use Yuhzel\TmController\App\Aseco;

class RaceManager
{
    public function beginChallenge(Container $call): void
    {
        Aseco::consoleText('Race started.');
    }

    public function endRace(Container $call): void
    {
        Aseco::consoleText('Race ended.');
    }
}
