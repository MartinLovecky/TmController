<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\App\Service\{Aseco, DedimaniaClient, Sender, Server};
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class Dedimania
{
    private TmContainer $config;
    private int $dediLastSent = 0;
    private int $serverMaxRank = 30;
    private bool $requestDone = false;
    private const DOCS = 'https://www.gamers.org/tmf/docs/ListDedimania.html';

    protected ?ManiaLinks $maniaLinks = null;
    protected ?Checkpoints $checkpoints = null;

    public function __construct(
        private DedimaniaClient $dedimaniaClient,
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private Sender $sender,
    ) {
        $this->dediLastSent = time();
    }

    public function setRegistry(PluginManager $registry): void
    {
        $this->maniaLinks = $registry->maniaLinks;
        $this->checkpoints = $registry->checkpoints;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function onSync(): void
    {
        $configFile = Server::$jsonDir . 'dedimania.json';
        $this->config = TmContainer::fromJsonFile($configFile, false);
        $this->config
            ->set('masterserver_account.login', $_ENV['dedi_username'])
            ->set('masterserver_account.password', $_ENV['dedi_code'])
            ->set('masterserver_account.nation', $_ENV['dedi_nation']);
        Aseco::console('************* (Dedimania) *************');
        $this->dedimaniaConnect();
        Aseco::console('------------- (Dedimania) -------------');
        $this->dediLastSent = time();
    }

    /**
     * Undocumented function
     *
     * @param TmContainer $player
     * @return void
     */
    public function onPlayerConnect(TmContainer $player)
    {
        $response = $this->dedimaniaClient->request('playerArrive', $this->playerArrive($player));

        if (!$response) {
            return;
        }

        if (isset($response[1]['faultString'])) {
            Aseco::console('dedimania_playerconnect - error(s): {1}', $response);
            return;
        }
        $this->dediLastSent = time();
        $this->requestDone = true;
        if ($this->config->get('database.show_welcome')) {
            $message = "{#server}{$this->config->get('database.welcome')}";
            $message = str_replace('{br}', "\n", $message);
            $message = str_replace('www.dedimania.com', '$l[http://www.dedimania.com/]www.dedimania.com$l', $message);

            $this->sender->sendChatMessageToLogin(
                login: $player->get('Login'),
                message: $message,
                formatMode: Sender::FORMAT_COLORS
            );
        }
        //NOTE: idk if this will ever happen
        if ($response->has('1.data')) {
            dd($response);
        }
    }

    public function onPlayerDisconnect()
    {
    }

    /**
     *
     * @return void
     */
    public function onEverySecond(): void
    {
        if ($this->requestDone) {
            $this->dedimaniaAnnounce();
            return;
        } else {
            $this->dedimaniaConnect();
            return;
        }
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function onNewChallenge(): void
    {
        $response = $this->dedimaniaClient->request(
            'currentChallenge',
            $this->currentChallenge($this->challengeService->getChallenge())
        );

        if (!$response || $this->challengeService->getGameMode() === 'stunts') {
            return;
        }

        /** @var array<string,array> $checkpoints */
        $checkpoints = $this->checkpoints->checkpoints;
        /** @var array<int,TmContainer> $records */
        $records = $response->get('1.Records');

        if (empty($records)) {
            return;
        }

        // Normalize nicknames
        foreach ($records as $rec) {
            $rec->set('NickName', str_replace("\n", '', $rec->get('NickName')));
        }

        foreach ($checkpoints as $login => &$cp) {
            // Try to find the player's own record
            $record = null;
            foreach ($records as $rec) {
                if ($rec->get('Login') === $login) {
                    $record = $rec;
                    break;
                }
            }

            // If no personal record, fall back to best (first)
            if ($record === null) {
                $record = $records[0];
            }

            $cp['bestFin'] = $record->get('Best');    // Best time
            $cp['bestCps'] = $record->get('Checks');  // Checkpoints

            $time = Aseco::formatTime($cp['bestFin']);

            // Store in ManiaLinks global
            $maniaLinks = $this->maniaLinks->mlRecords;
            $maniaLinks['dedi'] = $time;

            // Get player object
            $player = $this->playerService->getPlayerByLogin($login);

            // Determine player's own record time
            $ownRecordTime = $record && $record->get('Best') > 0
                ? Aseco::formatTime($record->get('Best'))
                : ('   --.--');

            // Display panel
            $this->maniaLinks->displayRecPanel($player, $ownRecordTime);
        }
    }

    public function onEndRace(TmContainer $data)
    {
        $maniaLinks = $this->maniaLinks->mlRecords;
        $maniaLinks['dedi'] = '   --.--';
        if ($this->challengeService->getGameMode() === 'stunts') {
            return;
        }

        dd($data);
    }

    public function onPlayerFinish()
    {
    }

    public function onPlayerManialinkPageAnswer()
    {
    }

    private function dedimaniaConnect(): void
    {
        $cfg = $this->config;
        Aseco::console("* Dataserver connection on {$cfg->get('database.name')} ...");
        Aseco::console("* Try connection on {$cfg->get('database.url')}");

        $response = $this->dedimaniaClient->request('validate', $this->validateAccount());

        if (!$response) {
            Aseco::consoleText('!!! Error bad Dedimania response !!!');
            return;
        }

        if (!$this->dedimaniaAuthenticate()) {
            Aseco::consoleText('!!! Failed to validate and Authenticate master account !!!');
            return;
        }

        Aseco::console('Dedimania Connection and status ok!');
        $this->requestDone = true;
        return;
    }

    private function dedimaniaAuthenticate(): bool
    {
        return $this->dedimaniaClient->request('authenticate', $this->auth())->get('0');
    }

    private function dedimaniaAnnounce(): void
    {
        $this->dedimaniaClient->request(
            'UpdateServerPlayers',
            $this->updateServerPlayers()
        );
        $this->dediLastSent = time();
        $this->requestDone = true;
    }

    private function auth(): array
    {
        return [
            $this->authCall(),
            [
                'methodName' => 'dedimania.ValidateAccount',
                'params' => []
            ],
            $this->warrnings()
        ];
    }

    private function playerArrive(TmContainer $player): array
    {
        return [
            $this->authCall(),
            [
                'methodName' => 'dedimania.PlayerArrive',
                'params' => [
                    'Game' => Server::$game,
                    'Login' => $player->get('Login'),
                    'Nation' => $player->get('Nation'),
                    'Nickname' => $player->get('NickName'),
                    'TeamName' => $player->get('LadderStats.TeamName'),
                    'LadderRanking' => $player->get('LadderStats.PlayerRankings.0.Ranking'),
                    'IsSpectator' => $player->get('IsSpectator'),
                    'IsOfficial' => $player->get('IsInOfficialMode'),
                ]
            ],
            $this->warrnings()
        ];
    }

    private function currentChallenge(TmContainer $challenge): array
    {
        return [
            $this->authCall(),
            [
                'methodName' => 'dedimania.CurrentChallenge',
                'params' => [
                    'Uid' => $challenge->get('UId'),
                    'Name' => $challenge->get('Name'),
                    'Environment' => $challenge->get('Environnement'),
                    'Author' => $challenge->get('Author'),
                    'Game' => Server::$game,
                    'Mode' => $challenge->get('Options.GameMode'),
                    'SrvInfos' => $this->serverInfo(),
                    'MaxGetTimes' => $this->serverMaxRank,
                    'Players' => $this->players()
                ]
            ],
            $this->warrnings()
        ];
    }

    private function validateAccount(): array
    {
        return [
            [
                'methodName' => 'dedimania.ValidateAccount',
                'params' => []
            ],
            $this->warrnings()
        ];
    }

    private function updateServerPlayers(): array
    {
        return [
            [
                'methodName' => 'dedimania.UpdateServerPlayers',
                'params' => [
                    'Game' => Server::$game,
                    'Mode' => 1,//TODO: add map mode in challengeService return int
                    'SrvInfo' => $this->serverInfo(),
                    'Players' => $this->players()
                ]
            ],
            $this->warrnings()
        ];
    }

    private function serverInfo(): array
    {
        return[
            'SrvName' => Server::$name,
            'Comment' => Server::$comment,
            'Private' => Server::$private,
            'SrvIP' => '',
            'SrvPort' => 0,
            'XmlrpcPort' => 0,
            'NumPlayers' => $this->playerService->numPlayers,
            'MaxPlayers' => Server::$maxPlayers,
            'NumSpecs' => $this->playerService->numSpecs,
            'MaxSpecs' => Server::$maxSpectators,
            'LadderMode' => Server::$ladderMode,
            'NextFiveUID' => $this->challengeService->getNextUid()
        ];
    }

    private function players(): array
    {
        $info = [];
        $this->playerService->eachPlayer(function (TmContainer $player) {
            $info[] = [
                'Game' => Server::$game,
                'Login' => $player->get('Login'),
                'Nation' => $player->get('Nation'),
                'TeamName' =>  $player->get('LadderStats.TeamName'),
                'TeamId' => -1,
                'Ranking' => $player->get('LadderStats.PlayerRankings.0.Ranking'),
                'IsSpec' => $player->get('IsSpectator'),
                'IsOff' => $player->get('IsInOfficialMode')
            ];
        });
        return $info;
    }

    private function authCall(): array
    {
        return [
            'methodName' => 'dedimania.Authenticate',
            'params' => [[
                'Game' => Server::$game,
                'Login' => $this->config->get('masterserver_account.login'),
                'Password' => $this->config->get('masterserver_account.password'),
                'Tool' => 'Xaseco',
                'Version' => '1.16',
                'Nation' =>  $this->config->get('masterserver_account.nation'),
                'Packmask' => Server::$packmask //'United'
            ]]
        ];
    }

    private function warrnings(): array
    {
        return [
            'methodName' => 'dedimania.WarningsAndTTR',
            'params' => []
        ];
    }
}
