<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Rasp;

use Yuhzel\TmController\App\Service\{Aseco, Log, Sender};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\{ManiaLinks, Track};
use Yuhzel\TmController\Plugins\Rasp\{RaspState};
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class RaspManager
{
    private ?ManiaLinks $maniaLinks = null;
    private ?Track $track = null;

    public function __construct(
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private RaspState $raspState,
        private Sender $sender,
    ) {
    }

    public function setDependecy(ManiaLinks $maniaLinks, Track $track): void
    {
        $this->maniaLinks = $maniaLinks;
        $this->track = $track;
    }

    public function startVote(TmContainer $player, string $type, string $desc, float $ratio): void
    {
        $login = $player->get('Login');
        $requiredVotes = $this->calculateRequiredVotes($ratio);

        $this->raspState->chatvote = new RaspVoteDTO([
            'login' => $login,
            'nick'  => $player->get('NickName'),
            'votes' => $requiredVotes,
            'type'  => $type,
            'desc'  => $desc,
        ]);

        $this->raspState->plrvotes = [];
        $this->raspState->ta_expire_start = $this->track->timePlaying();
        $this->raspState->r_expire_num = 0;
        $this->raspState->ta_show_num = 0;

        $this->sender->sendChatMessageToAll(
            message: Aseco::getChatMessage('vote_start', 'rasp'),
            formatArgs: [
                $this->raspState->chatvote->nick,
                $desc,
                $this->raspState->chatvote->votes
            ]
        );

        Log::debug('Rasp', [Aseco::getChatMessage('vote_start', 'rasp')], 'Rasp');

        $this->maniaLinks->displayVotePanel(
            player: $player,
            yes: '$333Yes - F5',
            no: '$333No - F6',
            formatMode: Sender::FORMAT_NONE
        );
    }

    public function resetVotes(): void
    {
        if ($this->raspState->chatvote) {
            $this->sender->query('ChatSendServerMessage', ["Vote canceled"]);
            $this->raspState->chatvote = null;
        }
        $this->maniaLinks->allVotePanelsOff();
    }

    private function calculateRequiredVotes(float $ratio): int
    {
        $activePlayers = $this->playerService->getActivePlayersCount($this->raspState->allow_spec_voting);

        $votes = $activePlayers <= 7 ? (int)round($activePlayers * $ratio) : (int)floor($activePlayers * $ratio);
        $votes = max(1, $votes);

        // Prevent 1-player vote when only 2-3 players
        if ($activePlayers >= 2 && $activePlayers <= 3 && $votes === 1) {
            $votes = 2;
        }

        return $votes;
    }
}
