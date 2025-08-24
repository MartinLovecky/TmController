<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Plugins\Rasp\{RaspState, VoteCommandHandler, VoteType, VoteManager};
use Yuhzel\TmController\Plugins\{ManiaLinks, Track, RaspJukebox};
use Yuhzel\TmController\Repository\PlayerService;

class RaspVotes
{
    private VoteCommandHandler $voteHandler;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected ManiaLinks $maniaLinks,
        protected RaspJukebox $raspJukebox,
        protected RaspState $raspState,
        protected VoteManager $voteManager,
        protected Track $track,
    ) {
        $this->voteHandler = new VoteCommandHandler(
            $this->voteManager,
            $this->raspState,
            $this->client,
            $this->challengeService,
            $this->playerService,
            $this->maniaLinks,
            $this->track
        );
    }

    public function handleChatCommand(TmContainer $player): void
    {
        match (strtolower($player->get('command.name'))) {
            VoteType::ENDROUND->value => $this->voteHandler->handleEndRound($player),
            VoteType::LADDER->value   => $this->voteHandler->handleLadder($player),
            VoteType::SKIP->value     => $this->voteHandler->handleSkip($player),
            VoteType::REPLAY->value   => $this->voteHandler->handleReplay($player),
            VoteType::KICK->value     => $this->voteHandler->handleKick($player),
            VoteType::HELP->value     => $this->voteHandler->handleHelp($player),
            VoteType::CANCEL->value   => $this->voteHandler->handleVoteCancel($player),
            default                   => null,
        };
    }

    public function onSync(): void
    {
        $this->client->query('SetCallVoteRatios', [[['Command' => '*', 'Ratio' => -1.0]]]);
        $this->voteManager->resetVotes();
    }

    public function onEndRace(): void
    {
        $this->voteManager->resetVotes();
    }

    public function onNewChallenge(): void
    {
        $this->raspState->disabled_scoreboard = false;
    }

    public function onEndRound(): void
    {
        if ($this->challengeService->getGameMode() !== 'rounds') {
            return;
        }

        if ($this->raspState->chatvote?->type === VoteType::ENDROUND->value) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('vote_end', 'rasp'),
                $this->raspState->chatvote->desc,
                'expired',
                'Server'
            );
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
            $this->voteManager->resetVotes();
        }
    }

    public function onCheckpoint(): void
    {
        if ($this->raspState->chatvote) {
            $expireLimit = $this->raspState->ta_expire_limit[$this->raspState->chatvote->type] ?? 90;
            $played = $this->track->timePlaying();

            if (($played - $this->raspState->ta_expire_start) >= $expireLimit) {
                $message = Aseco::formatText(
                    Aseco::getChatMessage('vote_end', 'rasp'),
                    $this->raspState->chatvote->desc,
                    'expired',
                    'Server'
                );
                $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
                $this->voteManager->resetVotes();
            }
        }
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        if (!$this->raspState->feature_votes) {
            return;
        }

        $message = Aseco::getChatMessage('vote_explain', 'rasp');
        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
        ]);
    }

    public function onPlayerDisconnect(TmContainer $player): void
    {
        if ($this->raspState->chatvote && $this->raspState->chatvote->login === $player->get('Login')) {
            $this->voteManager->resetVotes($player);
        }
    }
}
