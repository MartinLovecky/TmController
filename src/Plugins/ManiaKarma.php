<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Server;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Karma\{
    Auth,
    Command,
    Vote,
    State,
    Widget
};

class ManiaKarma
{
    public function __construct(
        protected Auth $auth,
        protected Command $command,
        protected State $state,
        protected Vote $vote,
        protected Widget $widget,
    ) {
    }

    public function onSync(): void
    {
        $this->auth->authenticate();
        $this->state->initializeKarmaState();
        //$this->widget->getTemplate();
        $this->widget->sendInitialWidgets();
    }

    public function onChat(TmContainer $chat): void
    {
        if ($chat->get('0') === Server::$id) {
            return;
        }
        $this->command->handleChatCommand($chat);
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        $this->widget->sendWelcomeMessage($player);
        $this->command->handleAdminFeatures($player);
        $this->state->initializePlayerState($player);
        $this->widget->updatePlayerWidgets($player);
    }

    public function onPlayerDisconnect(TmContainer $player): void
    {
        $this->vote->storeKarmaVotes();
        $this->widget->closeReminderWindow($player);
    }

    public function onPlayerFinish(object $finish_item)
    {
        if ($finish_item->score == 0) {
            return;
        }
    }

    public function onNewChallenge(): void
    {
        $this->state->resetForNewChallenge();
        $this->widget->hideAllWidgets();
    }

    public function onNewChallenge2()
    {
    }

    public function onRestartChallenge2()
    {
    }

    public function onEndRace1()
    {
    }

    public function onEverySecond(): void
    {
        //I have no clue wtf is going on
    }

    public function onPlayerLink(array $answer): void
    {
        $this->vote->handleVoteResponse($answer);
    }

    public function onKarmaChange()
    {
    }

    public function onShutdown(): void
    {
        $this->vote->storeKarmaVotes();
    }
}
