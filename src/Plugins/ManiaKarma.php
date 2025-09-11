<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Karma\KarmaAuth;
use Yuhzel\TmController\Plugins\Karma\KarmaSystem;
use Yuhzel\TmController\Plugins\Karma\KarmaVote;

class ManiaKarma
{
    public function __construct(
        private KarmaAuth $karmaAuth,
        private KarmaSystem $karmaSystem,
        private KarmaVote $karmaVote
    ) {
    }

    public function onSync(): void
    {
        $this->karmaAuth->authenticate();
        $this->karmaSystem->sendInitialWidgets();
    }

    public function handleChatCommand(TmContainer $player): void
    {
        $command = $player->get('command.name');
        $parms = $player->get('command.params');

        if (!in_array($command, KarmaVote::ALLOWED_COMMANDS, true)) {
            return;
        }

        if (str_starts_with('/', $command)) {
            //$this->karmaAdminController->handleChatCommand($chat);
        } elseif ($command === 'karma' && $parms === 'help') {
            //$this->karmaAdminController->sendHelp($chat);
        } else {
            $this->karmaVote->startVote($command, $player->get('Login'));
            //$this->karmaSystem->updateAllPlayerWidgets();
        }
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        $this->karmaSystem->sendWelcomeMessage($player);
        $this->karmaSystem->updatePlayerWidgets($player);
    }
}
