<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Server;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Karma\{
    KarmaAuth,
    KarmaAdminController,
    KarmaVote,
    KarmaState,
    KarmaSystem
};

class ManiaKarma
{
    public function __construct(
        private KarmaAuth $auth,
        private KarmaAdminController $command,
        private KarmaState $state,
        private KarmaVote $vote,
        private KarmaSystem $widget
    ) {
    }

    // =================== Server Sync ===================
    public function onSync(): void
    {
        $this->auth->authenticate();

        // Initialize karma state
        $this->state->setKarmaState([
            'global' => ['votes' => [], 'players' => []],
            'local'  => ['votes' => [], 'players' => []],
            'new'    => ['players' => []],
            'data'   => []
        ]);

        $this->widget->sendInitialWidgets();
    }

    // =================== Chat Commands ===================
    public function onChat(TmContainer $chat): void
    {
        if ($chat->get('0') === Server::$id) {
            return;
        }

        // Admin commands
        $this->command->handleChatCommand($chat);

        // Player vote commands
        $this->vote->handleChatCommand($chat);

        // Update widgets for all players after any vote
        $this->widget->updateAllPlayerWidgets();
    }

    // =================== Player Connect ===================
    public function onPlayerConnect(TmContainer $player): void
    {
        $login = $player->get('Login');
        $this->state->updatePlayerVote($login, 0, 0);
        $this->widget->sendWelcomeMessage($player);
        $this->widget->updatePlayerWidgets($player);
    }

    // =================== Player Disconnect ===================
    public function onPlayerDisconnect(TmContainer $player): void
    {
        $this->vote->storeVotesToAPI();
        $this->widget->closeReminderWindow($player);
    }

    // =================== Race / Challenge Hooks ===================
    public function onPlayerFinish(object $finish_item): void
    {
        if ($finish_item->score === 0) {
            return;
        }
        // Optional: additional logic based on finish score
    }

    public function onNewChallenge(): void
    {
        $this->state->clearNewVotes();
        $this->widget->hideAllWidgets();
    }

    public function onRestartChallenge2(): void
    {
        // Optional: handle restart logic
    }

    public function onEndRace1(): void
    {
        // Optional: handle end-of-race logic
    }

    // =================== Periodic Updates ===================
    public function onEverySecond(): void
    {
        // Retry sending votes if failed previously
        $this->vote->storeVotesToAPI();
    }

    // =================== Player Link / Karma Changes ===================
    public function onPlayerLink(TmContainer $answer): void
    {
        $this->vote->handleVoteResponse($answer);
        $this->widget->updateAllPlayerWidgets();
    }

    public function onKarmaChange(): void
    {
        $this->widget->updateAllPlayerWidgets();
    }

    // =================== Shutdown ===================
    public function onShutdown(): void
    {
        $this->vote->storeVotesToAPI();
    }
}
