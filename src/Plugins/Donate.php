<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;

class Donate
{
    public array $bills = [];
    public array $payments = [];
    public int $minDonation = 10;
    public int $publicappr = 100;
    public array $danationValues = [];

    public function chatDonate(TmContainer $player)
    {
        $login = $player->get('Login');
        $coppers = $player->get('command.params');
        // IF NOT TMU ACC
        if ($player->get('OnlineRights') !== 3) {
        }
    }
}
