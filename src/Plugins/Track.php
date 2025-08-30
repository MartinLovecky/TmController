<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Repository\ChallengeService;

class Track
{
    public function __construct(private ChallengeService $challengeService)
    {
    }

    public function timePlaying(): int
    {
        return time() - $this->challengeService->getChallenge()->get('startTime');
    }
}
