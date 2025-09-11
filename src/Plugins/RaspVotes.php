<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Log;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\{Aseco, Sender};
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};
use Yuhzel\TmController\Plugins\Rasp\{RaspCommandHandler, RaspState, RaspManager, RaspVoteType};

class RaspVotes
{
    private ?Track $track = null;

    public function __construct(
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private RaspCommandHandler $voteHandler,
        private RaspManager $voteManager,
        private RaspState $raspState,
        private Sender $sender,
    ) {
    }

    public function setRegistry(PluginManager $pluginManager): void
    {
        $this->track = $pluginManager->track;
        // TODO (yuha) Refactor if plugin grows, as smaller subcomponents may still depend on other plugins.
        // That is bit complicated and I dont want deal with it
        $this->voteHandler->setDependecy($pluginManager->maniaLinks, $pluginManager->track);
        $this->voteManager->setDependecy($pluginManager->maniaLinks, $pluginManager->track);
    }

    public function handleChatCommand(TmContainer $player): void
    {
        Log::debug("handleChatCommand - skip", [RaspVoteType::SKIP->value, $player->get('command.name')], 'skip');
        match ($player->get('command.name')) {
            RaspVoteType::SKIP->value => $this->voteHandler->skip($player),
            RaspVoteType::HELP->value => $this->voteHandler->help($player),
            default => null
        };
    }

    public function onSync(): void
    {
        $this->sender->query('SetCallVoteRatios', [[['Command' => '*', 'Ratio' => -1.0]]]);
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


    public function onPlayerConnect(TmContainer $player): void
    {
        if (!$this->raspState->feature_votes) {
            return;
        }

        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: Aseco::getChatMessage('vote_explain', 'rasp'),
            formatMode: Sender::FORMAT_COLORS
        );
    }

    public function onPlayerDisconnect(TmContainer $player): void
    {
        if ($this->raspState->chatvote?->login === $player->get('Login')) {
            $this->voteManager->resetVotes();
        }
    }

    public function onEndRound(): void
    {
        if ($this->challengeService->getGameMode() !== 'rounds') {
            return;
        }

        $this->checkVoteExpiry(RaspVoteType::ENDROUND->value, 'expired');

        $chatVote = $this->raspState->chatvote;

        if (isset($chatVote)) {
        }
    }

    //TODO refactor
    public function onCheckpoint(): void
    {
        $chatVote = $this->raspState->chatvote;

        if (!isset($chatVote)) {
            return;
        }

        $gamemode = $this->challengeService->getGameMode();

        if ($gamemode == 'rounds' || $gamemode == 'team' || $gamemode == 'cup') {
            return;
        }

        $expireLimit = $this->raspState->ta_expire_limit[$chatVote->type] ?? 90;
        $played = $this->track->timePlaying();

        if (($played - $this->raspState->ta_expire_start) >= $expireLimit) {
            $this->checkVoteExpiry($chatVote->type, 'expired');
        } else {
            if ($this->raspState->ta_show_reminder) {
                $intervals = floor(($played - $this->raspState->ta_expire_start) / $this->raspState->ta_show_interval);

                if ($intervals > $this->raspState->ta_show_num) {
                    $this->raspState->ta_show_num = $intervals;
                }
                if (isset($chatVote->votes)) {
                    $arg = $chatVote->votes == 1 ? '' : 's';

                    $this->sender->sendChatMessageToAll(
                        message: Aseco::getChatMessage('vote_y', 'rasp'),
                        formatArgs: [$arg, $chatVote->desc]
                    );
                }
            }
        }
    }

    private function checkVoteExpiry(string $type, string $status): void
    {
        if ($this->raspState->chatvote?->type === $type) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('vote_end', 'rasp'),
                $this->raspState->chatvote->desc,
                $status,
                'Server'
            );

            $this->sender->sendChatMessageToAll(
                message: $message,
                formatMode: Sender::FORMAT_COLORS
            );
            $this->voteManager->resetVotes();
        }
    }
}
