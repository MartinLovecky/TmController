<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Rasp;

use Yuhzel\TmController\Plugins\Track;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Plugins\Rasp\Vote;
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\Plugins\Rasp\RaspState;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;

class VoteManager
{
    public function __construct(
        private RaspState $raspState,
        private Client $client,
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private Track $track,
        private ManiaLinks $maniaLinks
    ) {
    }

    public function startVote(TmContainer $player, string $type, string $desc, float $ratio): void
    {
        $login = $player->get('Login');
        $requiredVotes = $this->calculateRequiredVotes($ratio);

        $this->raspState->chatvote = new Vote([
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

        $message = Aseco::formatText(
            Aseco::getChatMessage('vote_start', 'rasp'),
            Aseco::stripColors($this->raspState->chatvote->nick),
            $desc,
            $this->raspState->chatvote->votes
        );

        $this->client->query('ChatSendServerMessage', [Aseco::formatColors(str_replace('{br}', "\n", $message))]);
        $this->maniaLinks->displayVotePanel(
            $player,
            Aseco::formatColors('{#vote}Yes - F5'),
            '$333No - F6'
        );
    }

    public function resetVotes(): void
    {
        if ($this->raspState->chatvote) {
            $this->client->query('ChatSendServerMessage', ["Vote canceled"]);
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
