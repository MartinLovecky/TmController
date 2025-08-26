<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Plugins\Track;
use Yuhzel\TmController\Plugins\Rasp\{RaspState, VoteCommandHandler, VoteType, VoteManager};

class RaspVotes
{
    protected ?Track $track = null;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected RaspState $raspState,
        protected VoteCommandHandler $voteHandler,
        protected VoteManager $voteManager,
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
        $command = strtolower($player->get('command.name'));
        foreach (VoteType::cases() as $voteType) {
            if ($voteType->value === $command) {
                $method = 'handle' . ucfirst($voteType->value);
                if (method_exists($this->voteHandler, $method)) {
                    $this->voteHandler->$method($player);
                }
                return;
            }
        }
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

        $this->checkVoteExpiry(VoteType::ENDROUND->value, 'expired');
    }

    public function onCheckpoint(): void
    {
        if (!$this->raspState->chatvote) {
            return;
        }
        $type = $this->raspState->chatvote->type;
        $expireLimit = $this->raspState->ta_expire_limit[$type] ?? 90;
        $played = $this->track->timePlaying();

        if (($played - $this->raspState->ta_expire_start) >= $expireLimit) {
            $this->checkVoteExpiry($type, 'expired');
        }
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        if (!$this->raspState->feature_votes) {
            return;
        }

        $this->sendMessage($player, Aseco::getChatMessage('vote_explain', 'rasp'));
    }

    public function onPlayerDisconnect(TmContainer $player): void
    {
        if ($this->raspState->chatvote?->login === $player->get('Login')) {
            $this->voteManager->resetVotes();
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
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
            $this->voteManager->resetVotes();
        }
    }

    private function sendMessage(TmContainer $player, string $message): void
    {
        $this->client->query('ChatSendServerMessageToLogin', [Aseco::formatColors($message), $player->get('Login')]);
    }
}
