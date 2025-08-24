<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\App\Service\{Aseco, HttpClient, Server};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Karma\Vote;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Infrastructure\Xml\Parser;

class Command
{
    public function __construct(
        protected Client $client,
        protected HttpClient $httpClient,
        protected PlayerService $playerService,
        protected Vote $vote,
    ) {
    }

    public function handleChatCommand(TmContainer $chat): void
    {
        $login = $chat['login'];
        $text = trim($chat['message']);
        $player = $this->playerService->getPlayerByLogin($login);

        if (strpos($text, '/karma') === 0 && Aseco::isAnyAdmin($login)) {
            $this->processKarmaCommand($player, $text);
        }
    }

    private function processKarmaCommand(TmContainer $player, string $command): void
    {
        $parts = explode(' ', $command);
        $subCommand = $parts[1] ?? 'help';

        switch ($subCommand) {
            case 'export':
                $this->handleExportCommand($player);
                break;
            case 'help':
                $this->sendHelpMessage($player);
                break;
            default:
                $this->sendCommandList($player);
        }
    }

    public function handleAdminFeatures(TmContainer $player): void
    {
        if (!Aseco::isAnyAdmin($player->get('Login'))) {
            return;
        }

        $this->uptodateCheck($player);

        if (!$this->cfg()->get('import_done')) {
            $this->notifyAdminAboutExport($player);
        }
    }

    private function handleExportCommand(TmContainer $player): void
    {
        $message = "Exporting karma data to central database...";
        $this->sendChatMessage($player, $message);

        $this->vote->storeKarmaVotes();
        //$this->cfg->set('import_done', true);
    }

    private function sendHelpMessage(TmContainer $player): void
    {
        $help = $this->cfg()->get('messages.karma_help');
        $formattedHelp = str_replace('{br}', "\n", Aseco::formatColors($help));
        $this->sendChatMessage($player, $formattedHelp);
    }

    private function sendCommandList(TmContainer $player): void
    {
        $commands = [
            '/karma export - Export karma data to central database',
            '/karma help - Show karma help information'
        ];

        $message = "Available karma commands:\n" . implode("\n", $commands);
        $this->sendChatMessage($player, $message);
    }

    private function notifyAdminAboutExport(TmContainer $player): void
    {
        $message = "{#server}> {#emotic}#################################################\n";
        $message .= "{#server}> {#emotic}start the export with the command /karma export\n";
        $message .= "{#server}> {#emotic}#################################################";

        $this->sendChatMessage($player, $message);
    }

    private function sendChatMessage(TmContainer $player, string $message): void
    {
        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
        ]);
    }

    private function uptodateCheck(TmContainer $player): void
    {
        $response = $this->httpClient->post('www.mania-karma.com/api/plugin-releases.xml');
        $output = Parser::fromXMLString($response);

        if (version_compare($output->get('xaseco1xx'), '2.0.1', '>')) {
            $this->notifyAboutUpdate($player, $output);
        } else {
            $this->confirmUpToDate($player);
        }
    }

    private function notifyAboutUpdate(TmContainer $player, TmContainer $output): void
    {
        $message = Aseco::formatText(
            $this->cfg()->get('messages.uptodate_new'),
            $output->get('xaseco1xx'),
            '$L[http://www.mania-karma.com/Downloads/]http://www.mania-karma.com/Downloads/$L'
        );
        $this->sendChatMessage($player, $message);
    }

    private function confirmUpToDate(TmContainer $player): void
    {
        $message = Aseco::formatText($this->cfg()->get('messages.uptodate_ok'), '2.0.1');
        $this->sendChatMessage($player, $message);
    }

    private function cfg(): TmContainer
    {
        return TmContainer::fromJsonFile(Server::$jsonDir . 'maniaKarma.json');
    }
}
