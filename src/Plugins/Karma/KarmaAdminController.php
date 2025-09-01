<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Karma\KarmaManager;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\App\Service\{Aseco, Sender};

class KarmaAdminController
{
    public function __construct(
        private KarmaManager $karmaManager,
        private PlayerService $playerService,
        private Sender $sender
    ) {
    }

    public function handleChatCommand(TmContainer $player): void
    {
        $cmdName = $player->get('command.name');

        if (strpos($cmdName, '/karma') === 0 && Aseco::isAnyAdmin($player->get('Login'))) {
            $cmdParams = empty($player->get('command.params')) ? 'help' :  $player->get('command.params');
            $this->processKarmaCommand($player, $cmdParams);
        }
    }

    private function processKarmaCommand(TmContainer $player, string $command): void
    {
        match ($command) {
            'export' => $this->exportKarma($player),
            'help' => $this->sendHelp($player),
            default => $this->sendCommandList($player)
        };
    }

    private function exportKarma(TmContainer $player): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: 'Exporting karma data to central database...',
            formatMode: Sender::FORMAT_NONE
        );
        $this->karmaManager->exportVotes();
    }

    private function sendHelp(TmContainer $player): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: $this->cfg('messages.karma_help'),
            formatMode: Sender::FORMAT_COLORS
        );
    }

    private function sendCommandList(TmContainer $player): void
    {
        $commands = [
            '//karma export - Export karma data to central database',
            '//karma help - Show karma help information'
        ];

        $message = "Available karma commands:\n" . implode("\n", $commands);
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: $message,
            formatMode: Sender::FORMAT_COLORS
        );
    }

    private function cfg(string $path): mixed
    {
        return Aseco::getSetting($path, 'maniaKarma');
    }
}
