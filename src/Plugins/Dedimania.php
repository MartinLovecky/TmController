<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Services\DedimaniaClient;

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
        protected DedimaniaClient $dedimaniaClient
    ) {
        $this->dediLastSent = time();
        $configFile = Aseco::jsonFolderPath() . 'dedimania.json';
        $this->config = Container::fromJsonFile($configFile, false);
        $this->config
            ->set('masterserver_account.login', $_ENV['dedi_username'])
            ->set('masterserver_account.password', $_ENV['dedi_code'])
            ->set('masterserver_account.nation', $_ENV['dedi_nation'])
            ->set('maxRank', 30);
        Aseco::console('************* (Dedimania) *************');
        $this->dedimaniaLogin();
        Aseco::console('------------- (Dedimania) -------------');
    }

    public function onSync()
    {
        // ? $checkpoints
    }

    public function onPlayerConnect(Container $player)
    {
        $response = $this->dedimaniaClient->request('playerArrive', $this->playerArrive($player));

        if (isset($response[1]['faultString']) || !$response[1][0] instanceof Container) {
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

    public function onEverySecond()
    {
        if ($this->dediLastSent + $this->dediRefresh < time()) {
            $response = $this->dedimaniaClient->request(
                'UpdateServerPlayers',
                $this->updateServerPlayers()
            );
            dd($response);
        } else {
        }
    }

    private function dedimaniaLogin(): bool
    {
        $response = $this->dedimaniaClient->request('authenticate', $this->authenticate());

        if (!$response instanceof Container) {
            return false;
        }

        return $response->get('1.0.Status');
    }

    private function authenticate(): array
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

    private function updateServerPlayers(): array
    {
        $this->dediLastSent = time();
        return [
            'methodName' => 'dedimania.UpdateServerPlayers',
            'params' => [
                'Game' => 'TMU',
                'Mode' => null,
                'SrvInfo' => [],
                'Players' => []
            ]
        ];
    }

    private function authCall(): array
    {
        return [
            'methodName' => 'dedimania.Authenticate',
            'params' => [[
                'Game' => 'TMU',
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
