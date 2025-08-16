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
    private int $dediRefresh = 240;
    private int $dediMinAuth = 8000;
    private int $dediMinTime = 6000;
    private int $dediTimeOut = 1800;

    public function __construct(
        protected Client $client,
        protected DedimaniaClient $dedimaniaClient,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
    ) {
        $this->dediLastSent = time();
    }

    public function onSync(): void
    {
        $configFile = Aseco::jsonFolderPath() . 'dedimania.json';
        $this->config = Container::fromJsonFile($configFile, false);
        $this->config
            ->set('masterserver_account.login', $_ENV['dedi_username'])
            ->set('masterserver_account.password', $_ENV['dedi_code'])
            ->set('masterserver_account.nation', $_ENV['dedi_nation'])
            ->set('maxRank', 30);
        Aseco::console('************* (Dedimania) *************');
        $this->dedimaniaConnect();
        Aseco::console('------------- (Dedimania) -------------');
    }

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

        if ($this->config->get('database.show_welcome')) {
            $message = "{#server}{$this->config->get('database.welcome')}";
            $message = str_replace('{br}', "\n", $message);
            $message = str_replace('www.dedimania.com', '$l[http://www.dedimania.com/]www.dedimania.com$l', $message);
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message), $player->get('Login')
            ]);
        }

        // we need mod $player -> set dedirank with $dedi_db['MaxRank'];
        // update $dediRank with response + 0;
    }

    public function onNewChallenge()
    {
        // we need chall info and player info also gameInfo
        //$response = $this->dedimaniaClient->request('currentChallenge', $this->currentChallenge());
        //server info = ccParams
        //players = ccParams
        // we do some shit with dedi db uff maybe we can omit it not sure if that is case
        //dd($response);
    }

    public function onEverySecond(): void
    {
        $response = $this->dedimaniaClient->request(
            'UpdateServerPlayers',
            $this->updateServerPlayers()
        );

        dd($response);
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

        if (!$response->get('Status') || !$this->dedimaniaAuthenticate()) {
            Aseco::consoleText('!!! Failed to validate and Authenticate master account !!!');
            return;
        }

        Aseco::console('Dedimania Connection and status ok!');
        return;
    }

    private function dedimaniaAuthenticate(): bool
    {
        return $this->dedimaniaClient->request('authenticate', $this->auth());
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
                    'Nickname' => $player->get('NickName'),
                    'Nation' => $player->get('Nation'),
                    'TeamName' => $player->get('LadderStats.TeamName'),
                    'LadderRanking' => $player->get('LadderStats.PlayerRankings')[0]['Ranking'],
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
                'methodName' => 'dedimania.currentChallenge',
                'params' => [
                    $challenge->get('UId'),
                    $challenge->get('Environment'),
                    $challenge->get('Author'),
                    $challenge->get('Game'),
                    $challenge->get('Options.Mode'), //gameinfo
                    'serverinfo' => [
                        'SrvName' => $challenge->get('Options.Name'),
                        'Comment' => $challenge->get('Options.Comment'),
                        'Private' => $challenge->get('Options.Password', ''),
                        'SrvIP' => '',
                        'SrvPort' => 0,
                        'XmlrpcPort' => 0,
                        // This is propably wrong find $numplayers in org
                        'NumPlayers' => $challenge->get('Options.Numplayers'),
                        'MaxPlayers' => $challenge->get('Options.CurrentMaxPlayers'),//gameinfo
                        // This is propably wrong find $numspecs in org
                        'NumSpecs' => $challenge->get('Options.Numspecs'), // should be player ?
                        'MaxSpecs' => $challenge->get('Options.MaxSpecs'), // this is config
                        'LadderMode' => $challenge->get('Options.CurrentLadderMode'),
                        'NextFiveUID' => null //dedi_getnextuid($aseco) pain
                    ],
                    //dedi_db['MaxRank']
                    'players' => [
                        // FUCK we need player info we dont want loop tho
                        'Login' => null,
                        'Nation' => null,
                        'TeamName' =>  null,
                        'TeamId' => -1,
                        'IsSpec' => null,
                        'Ranking' => null,
                        'IsOff' => null
                    ]
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
                'Login' => $player->get('Login'),
                'Nation' => $player->get('Nation'),
                'TeamName' =>  $player->get('LadderStats.TeamName'),
                'TeamId' => -1,
                'IsSpec' => $player->get('IsSpectator'),
                'Ranking' => $player->get('LadderStats.PlayerRankings.0.Ranking'),
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
