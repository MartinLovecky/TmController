<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\DTO;

use Yuhzel\TmController\App\DTO\{
    ChatHandler,
    RaceManager,
    PlayerManager,
    ServerManager
};

class CallbackProcessor
{
    public function __construct(
        private ChatHandler $chatHandler,
        private RaceManager $raceManager,
        private PlayerManager $playerManager,
        private ServerManager $serverManager
    ) {
    }

    public function process(array $callbacks): void
    {
        foreach ($callbacks as $call) {
            switch ($call->type ?? '') {
                case 'TrackMania.BeginChallenge':
                    $this->raceManager->beginChallenge($call);
                    break;

                case 'TrackMania.EndRace':
                    $this->raceManager->endRace($call);
                    break;

                case 'TrackMania.PlayerConnect':
                    $this->playerManager->connect($call);
                    break;

                case 'TrackMania.PlayerDisconnect':
                    $this->playerManager->disconnect($call);
                    break;

                case 'TrackMania.PlayerChat':
                    $this->chatHandler->handle($call);
                    break;

                default:
                    $this->serverManager->broadcast("Unhandled callback: " . ($call->type ?? 'unknown'));
            }
        }
    }
}
