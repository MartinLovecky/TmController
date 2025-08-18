<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Services\DedimaniaClient;
use Yuhzel\TmController\Services\Server;

class Dedimania
{
    private Container $config;
    private int $dediLastSent = 0;
    private int $serverMaxRank = 30;
    private bool $requestDone = false;
    private const DOCS = 'https://www.gamers.org/tmf/docs/ListDedimania.html';

    public function __construct(
        protected Client $client,
        protected DedimaniaClient $dedimaniaClient,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
    ) {
        $this->dediLastSent = time();
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function onSync(): void
    {
        $configFile = Aseco::jsonFolderPath() . 'dedimania.json';
        $this->config = Container::fromJsonFile($configFile, false);
        $this->config
            ->set('masterserver_account.login', $_ENV['dedi_username'])
            ->set('masterserver_account.password', $_ENV['dedi_code'])
            ->set('masterserver_account.nation', $_ENV['dedi_nation']);
        Aseco::console('************* (Dedimania) *************');
        $this->dedimaniaConnect();
        Aseco::console('------------- (Dedimania) -------------');
    }

    /**
     * Undocumented function
     *
     * @param Container $player
     * @return void
     */
    public function onPlayerConnect(Container $player)
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
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message), $player->get('Login')
            ]);
        }
        //NOTE: idk if this will ever happen
        if ($response->has('1.data')) {
            dd($response);
        }
    }

    /**
     *
     * @return void
     */
    public function onEverySecond(): void
    {
        if ($this->requestDone) {
            $this->dedmimaniaAnnounce();
        } else {
            $this->dedimaniaConnect();
        }
    }

    public function onNewChallenge()
    {
        $response = $this->dedimaniaClient->request(
            'currentChallenge',
            $this->currentChallenge($this->challengeService->getChallenge())
        );

        if (!$response || $this->challengeService->getGameMode() === 'stunts') {
            return;
        }

        /** @var array<int,Container> $records */
        $records = $response->get('1.Records');
        if (!empty($records)) {
            foreach ($records as $rec) {
                $rec->set('NickName', str_replace("\n", '', $rec->get('NickName')));
            }

            dd($records);  //TODO plugin checkpoints
        }
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
        $this->dediLastSent = time();
        return;
    }

    private function dedimaniaAuthenticate(): bool
    {
        return $this->dedimaniaClient->request('authenticate', $this->auth())->get('0');
    }

    private function dedmimaniaAnnounce()
    {
        $this->dediLastSent = time();
        $response = $this->dedimaniaClient->request(
            'UpdateServerPlayers',
            $this->updateServerPlayers()
        );

        dd($response); //auth
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

    private function playerArrive(Container $player): array
    {
        return [
            $this->authCall(),
            [
                'methodName' => 'dedimania.PlayerArrive',
                'params' => [
                    'Game' => 'TMF',
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

    private function currentChallenge(Container $challenge): array
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
                    'Game' => 'TMF',
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
                    'Game' => 'TMF',
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
        $this->playerService->eachPlayer(function (Container $player) {
            $info[] = [
                'Game' => 'TMF',
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
                'Game' => 'TMF',
                'Login' => $this->config->get('masterserver_account.login'),
                'Password' => $this->config->get('masterserver_account.password'),
                'Tool' => 'Xaseco',
                'Version' => '1.16',
                'Nation' =>  $this->config->get('masterserver_account.nation'),
                'Packmask' => 'United'
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
