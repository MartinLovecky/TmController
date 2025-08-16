<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Karma\{
    AuthService,
    CommandService,
    VoteService,
    StateService,
    WidgetService
};
use Yuhzel\TmController\Services\Server;

class ManiaKarma
{
    public function __construct(
        protected AuthService $authService,
        protected CommandService $commandService,
        protected StateService $stateService,
        protected VoteService $voteService,
        protected WidgetService $widgetService,
    ) {
    }

    public function onSync(): void
    {
        $this->authService->authenticate();
        $this->stateService->initializeKarmaState();
        //$this->widgetService->getTemplate();
        $this->widgetService->sendInitialWidgets();
    }

    public function onChat(Container $chat): void
    {
        if ($chat->get('0') === Server::$id) {
            return;
        }
        $this->commandService->handleChatCommand($chat);
    }

    public function onPlayerConnect(Container $player): void
    {
        $this->widgetService->sendWelcomeMessage($player);
        $this->commandService->handleAdminFeatures($player);
        $this->stateService->initializePlayerState($player);
        $this->widgetService->updatePlayerWidgets($player);
    }

    public function onPlayerDisconnect(Container $player): void
    {
        $this->voteService->storeKarmaVotes();
        $this->widgetService->closeReminderWindow($player);
    }

    public function onPlayerFinish(object $finish_item)
    {
        if ($finish_item->score == 0) {
            return;
        }
    }

    public function onNewChallenge(): void
    {
        $this->stateService->resetForNewChallenge();
        $this->widgetService->hideAllWidgets();
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
        $this->voteService->handleVoteResponse($answer);
    }

    public function onKarmaChange()
    {
    }

    public function onShutdown(): void
    {
        $this->voteService->storeKarmaVotes();
    }
}
