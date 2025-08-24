<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Infrastructure\Gbx\Client;

class GameInfo
{
    public function __construct(protected Client $client)
    {
    }

    public function getCurrentGameInfo(): TmContainer
    {
        return $this->client->query('GetCurrentGameInfo', [1], true);
    }
}
