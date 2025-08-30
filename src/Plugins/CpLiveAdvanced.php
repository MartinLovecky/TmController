<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\ChallengeService;

class CpLiveAdvanced
{
    private int $numberCps = 0;
    private float $lastUpdate = 0.0;

    public function __construct(private ChallengeService $challengeService)
    {
    }

    public function onSync(): void
    {
        $this->numberCps = $this->challengeService->getGBX()->nbChecks - 1;
        $this->lastUpdate = microtime(true) * 1000;
    }

    // ???????
    public function onPlayerConnect(TmContainer $player)
    {
        $spectator = $player->get('IsSpectator', true);
        $this->bindToggleKey($player->get('Login'));
    }

    private function bindToggleKey(string $login)
    {
        $xml = '<manialink id="1928380">';
    }
}
