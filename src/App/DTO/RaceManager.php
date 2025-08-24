<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\DTO;

use League\Container\Container;
use Yuhzel\TmController\App\Service\Aseco;

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
