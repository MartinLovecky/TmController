<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services\Karma;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Infrastructure\Xml\Parser;
use Yuhzel\TmController\Services\HttpClient;

class CommandService
{
    public function __construct(
        protected Client $client,
        protected HttpClient $httpClient,
        protected PlayerService $playerService,
        protected VoteService $voteService,
    ) {
    }

    public function handleChatCommand(Container $chat): void
    {
        $login = $chat['login'];
        $text = trim($chat['message']);
        $player = $this->playerService->getPlayerByLogin($login);

        if (strpos($text, '/karma') === 0 && Aseco::isAnyAdmin($login)) {
            $this->processKarmaCommand($player, $text);
        }
    }

    private function processKarmaCommand(Container $player, string $command): void
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

    public function handleAdminFeatures(Container $player): void
    {
        if (!Aseco::isAnyAdmin($player->get('Login'))) {
            return;
        }

        $this->uptodateCheck($player);

        if (!$this->cfg()->get('import_done')) {
            $this->notifyAdminAboutExport($player);
        }
    }

    private function handleExportCommand(Container $player): void
    {
        $message = "Exporting karma data to central database...";
        $this->sendChatMessage($player, $message);

        $this->voteService->storeKarmaVotes();
        //$this->cfg->set('import_done', true);
    }

    private function sendHelpMessage(Container $player): void
    {
        $help = $this->cfg()->get('messages.karma_help');
        $formattedHelp = str_replace('{br}', "\n", Aseco::formatColors($help));
        $this->sendChatMessage($player, $formattedHelp);
    }

    private function sendCommandList(Container $player): void
    {
        $commands = [
            '/karma export - Export karma data to central database',
            '/karma help - Show karma help information'
        ];

        $message = "Available karma commands:\n" . implode("\n", $commands);
        $this->sendChatMessage($player, $message);
    }

    private function notifyAdminAboutExport(Container $player): void
    {
        $message = "{#server}> {#emotic}#################################################\n";
        $message .= "{#server}> {#emotic}start the export with the command /karma export\n";
        $message .= "{#server}> {#emotic}#################################################";

        $this->sendChatMessage($player, $message);
    }

    private function sendChatMessage(Container $player, string $message): void
    {
        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
        ]);
    }

    private function uptodateCheck(Container $player): void
    {
        $response = $this->httpClient->post('www.mania-karma.com/api/plugin-releases.xml');
        $output = Parser::fromXMLString($response);

        if (version_compare($output->get('xaseco1xx'), '2.0.1', '>')) {
            $this->notifyAboutUpdate($player, $output);
        } else {
            $this->confirmUpToDate($player);
        }
    }

    private function notifyAboutUpdate(Container $player, Container $output): void
    {
        $message = Aseco::formatText(
            $this->cfg()->get('messages.uptodate_new'),
            $output->get('xaseco1xx'),
            '$L[http://www.mania-karma.com/Downloads/]http://www.mania-karma.com/Downloads/$L'
        );
        $this->sendChatMessage($player, $message);
    }

    private function confirmUpToDate(Container $player): void
    {
        $message = Aseco::formatText($this->cfg()->get('messages.uptodate_ok'), '2.0.1');
        $this->sendChatMessage($player, $message);
    }

    private function cfg(): Container
    {
        return Container::fromJsonFile(Aseco::jsonFolderPath() . 'maniaKarma.json');
    }
}
