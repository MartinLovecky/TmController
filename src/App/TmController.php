<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Server;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};

class TmController
{
    public readonly Container $config;
    public ?Container $bannedIps = null;
    private int $prevsecond = 0;
    private int $currsecond = 0;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected RecordService $recordService,
        protected PluginManager $pluginManager,
        protected Server $server,
    ) {
        $this->prevsecond = time();
    }

    public function run(): void
    {
        Aseco::$startupPhase = true;
        $this->readConfig();
        $this->readAdminOps();
        $this->readBannedIps();
        $this->connect();
        $this->serverSync();
        $this->sendHeader();
        $this->beginRace(false);
        Aseco::$startupPhase = false;

        while (true) {
            $starttime = microtime(true);
            $this->executeCallbacks();
            //$this->executeCalls();
            $this->pluginManager->onMainLoop();

            $this->currsecond = time();
            if ($this->prevsecond !== $this->currsecond) {
                $this->prevsecond = $this->currsecond;
                $this->pluginManager->onEverySecond();
            }

            $endtime = microtime(true);
            $delay = 100000 - ($endtime - $starttime) * 1000000;
            if ($delay > 0) {
                usleep((int)$delay);
            }

            set_time_limit($this->config->get('script_timeout'));
        }

        $this->client->terminate();
    }

    private function readConfig(): void
    {
        $conf = Aseco::jsonFolderPath() . 'config.json';
        Aseco::console('[X8seco] Load settings from [{1}]', $conf);
        $this->config = Container::fromJsonFile($conf, true);
    }

    private function readAdminOps(): void
    {
        $ops = Aseco::jsonFolderPath() . 'adminops.json';
        Aseco::console('[X8seco] Load admin/ops lists [{1}]', $ops);
        Aseco::$adminOps = Container::fromJsonFile($ops);
    }

    private function readBannedIps(): void
    {
        $ipsFile = Aseco::jsonFolderPath() . 'bannedips.json';
        $bannedIps = Container::fromJsonFile($ipsFile);

        if (!$bannedIps->get('ban_list')->isEmpty()) {
            Aseco::console('[X8seco] Load banned IPs list [{1}]', $ipsFile);
            $this->bannedIps = $bannedIps;
        } else {
            unset($bannedIps);
        }
    }

    private function connect(): void
    {
        Aseco::console(
            'Trying to connect to TM dedicated server on {1}:{2} timeout {3}s',
            Server::$ip,
            Server::$port,
            Server::$timeout
        );

        Aseco::console('Trying to authenticate with login {1}', Server::$login);

        $this->client->query('EnableCallbacks', [true]);
        $this->waitServerReady();

        Aseco::console('Connection established successfully!');
    }

    private function waitServerReady(): void
    {
        $status = $this->client->query('GetStatus');

        if ($status->get('Code') !== 4) {
            Aseco::console('Waiting for dedicated server to reach status \'Running - Play\'...');
            Aseco::console('Status: {1}', $status->get('Name'));
            $timeout = 0;
            $lastStatus = $status->get('Name');
            while ($status->get('Code') !== 4) {
                sleep(1);
                $newStatus = $this->client->query('GetStatus')->get('Name');
                if ($lastStatus !== $newStatus) {
                    Aseco::console('Status: {1}', $newStatus);
                    $lastStatus = $newStatus;
                }
                if (Server::$timeout > $timeout++) {
                    trigger_error('Timed out while waiting for dedicated server!', E_USER_ERROR);
                }
            }
        }
    }

    private function serverSync(): void
    {
        $this->client->query('SendHideManialinkPage');
        $this->server->setServerInfo($this->playerService->first());
        $this->server->setVersion($this->client->query('GetVersion'));
        $this->server->setLadder($this->client->query('GetLadderServerLimits'));
        $this->server->setOptions($this->client->query('GetServerOptions'));

        $this->pluginManager->onSync();

        foreach ($this->playerService->getAllPlayers() as $_ => $player) {
            $this->playerConnect($player);
        }
    }

    private function playerConnect(Container $player)
    {
        if (!$this->bannedIps->isEmpty()) {
            foreach ($this->bannedIps as $_ => $ip) {
                if ($ip === $player->get('IPAddress')) {
                    return;
                }
            }
        }

        Aseco::console(
            '<< player {1} joined the game [{2} : {3} : {4} : {5} : {6}]',
            (string)$player->get('PlayerId'),
            (string)$player->get('Login'),
            (string)$player->get('NickName'),
            (string)$player->get('Path'),
            (string)$player->get('Raking'),
            (string)$player->get('IPAddress')
        );

        $version = str_replace(')', '', preg_replace('/.*\(/', '', $player->get('ClientVersion')));
        $message = Aseco::formatText(
            Aseco::getChatMessage('welcome'),
            Aseco::stripColors($player->get('Login')),
            Server::$name,
            $version
        );
        $message = preg_replace('/XASECO.+' . $version . '/', '$l[http://www.gamers.org/tmf]$0$l', $message);
        $message = str_replace('{br}', "\n", Aseco::formatColors($message));
        $message = str_replace("\n" . "\n", "\n", $message);

        $this->client->query('ChatSendServerMessageToLogin', [$message, $player->get('Login')]);

        //$chatCmd = $this->pluginManager->getPlugin('ChatCmd');
        //dd($chatCmd->trackrecs($player));
        $this->pluginManager->onPlayerConnect($player);
    }

    private function playerDisconnect(Container $player)
    {
        //NOTE: we cant rly remove from $player
        $playTime = time() - $player->get('created');
        Aseco::console(
            '>> player {1} left the game [{2} : {3}]',
            $player->get('Login'),
            $player->get('NickName'),
            Aseco::formatTimeH($playTime * 1000, false)
        );

        $this->pluginManager->onPlayerDisconnect($player);
    }

    private function sendHeader(): void
    {
        Aseco::console('###############################################################################');
        Aseco::console('  XASECO v 2.11.26 running on {1}:{2}', Server::$ip, Server::$port);
        Aseco::console(
            '  Name   : {1} - {2}',
            Aseco::stripColors(Server::$name, false),
            Server::$serverLogin
        );
        Aseco::console(
            '  Game   : {1} {2} - {3} - {4}',
            Server::$game,
            'United',
            Server::$packmask,
            'TimeAttack'
        );
        Aseco::console('  Version: {1} / {2}', Server::$version, Server::$build);
        Aseco::console('  Authors: Florian Schnell & Assembler Maniac');
        Aseco::console('  Re-Authored: Xymph');
        Aseco::console('  Remake: Yuhzel');
        Aseco::console('###############################################################################');

        $startupMsg = Aseco::formatText(
            Aseco::getChatMessage('startup'),
            '2.11.26',
            Server::$ip,
            Server::$port
        );

        $this->client->query('ChatSendServerMessage', [Aseco::formatColors($startupMsg)]);
    }

    private function beginRace(bool|Container $challenge)
    {
        if (!$challenge) {
            $this->newChallenge($this->challengeService->getCurrentChallengeInfo());
        } else {
            $this->newChallenge($challenge);
        }
    }

    private function newChallenge(Container $challenge): void
    {
        //this should happen only /restart
        //$this->pluginManager->onRestartChallenge($this->challengeService->getGBX());

        $this->pluginManager->onNewChallenge($this->challengeService);

        $curRecord = $this->recordService->getRecord($this->challengeService->getGBX()->UId)->get('Times');
        $message = '';

        if (!$curRecord->isEmpty()) {
            dd($curRecord);
            Aseco::console(
                'current record on {1} is {2} and held by {3}',
                Aseco::stripColors($gbx->name, false),
                trim('none'), // Aseco::formatTime($score)
                Aseco::stripColors('', false) // We need player Name
            );

            $message = Aseco::formatText(
                Aseco::getChatMessage('record_current'),
                Aseco::stripColors($gbx->name),
                trim(''), //$score
                Aseco::stripColors('') // $cur_record->player->nickname
            );
        } else {
            Aseco::console(
                'currently no record on {1}',
                Aseco::stripColors($challenge->get('Name'), false)
            );
            $message = Aseco::formatText(
                Aseco::getChatMessage('record_none'),
                Aseco::stripColors($challenge->get('Name'))
            );
        }

        if (($this->config->get('show_recs_before') & 1) === 1) {
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
        }

        $this->pluginManager->onNewChallenge2($this->challengeService->getGBX());

        //$chatCmd = $this->pluginManager->getPlugin('chatCmd');
        //$chatCmd->trackrecs();
    }

    private function executeCallbacks(): void
    {
        $this->client->readCallBack();

        $calls = $this->client->getCBResponses();

        if (empty($calls)) {
            return;
        }

        while ($call = array_shift($calls)) {
            $player = $this->playerService->getPlayerByLogin($call['2']);
            switch ($call->get('methodName')) {
                case 'TrackMania.PlayerConnect':
                    $this->playerConnect($player);
                    break;
                case 'TrackMania.PlayerDisconnect':
                    $this->playerDisconnect($player);
                    break;
                case 'TrackMania.PlayerChat':
                    $this->playerChat($call);
                    $this->pluginManager->onChat($call);
                    break;
                case 'TrackMania.PlayerServerMessageAnswer':
                    dd($call); // $this->pluginManager->onPlayerServerMessageAnswer()
                    break;
                case 'TrackMania.PlayerCheckpoint':
                    dd($call); // $this->pluginManager->onCheckpoint()
                    break;
                default:
            }
        }
    }

    private function playerChat(Container $call): void
    {
        $command = $call->get('2');
        $login = $call->get('1');
        $isAdmin = $call->get('3');

        if ($command == '' || $command == '???') {
            Aseco::console(
                '{1} attempted to use chat command "{2}"',
                $call->get('1'),
                $command
            );
        } elseif (str_starts_with($command, '/')) {
            $cmd = substr($command, 1);
            $params = explode(' ', $cmd, 2);
            $name = str_replace(['+', '-'], ['plus', 'dash'], $params[0]);

            if ($this->pluginManager->pluginFunctionExists("chat_{$name}")) {
                $params[1] = isset($params[1]) ? trim($params[1]) : '';
                $auth = $this->playerService->getPlayerByLogin($login);

                if ($auth instanceof Container) {
                    Aseco::console(
                        'player {1} used chat command "/{2} {3}"',
                        $login,
                        $params[0],
                        $params[1]
                    );

                    $cmdCommand = ['author' => $auth, 'params' => $params[1]];

                    $this->pluginManager->callPluginFunction("chat_{$name}", $cmdCommand);
                } else {
                    Aseco::console(
                        'player {1} attempted to use chat command "/{2} {3}"',
                        $login,
                        $params[0],
                        $params[1]
                    );
                    trigger_error("Player object for {$login} not found", E_USER_WARNING);
                }
            } else {
                Aseco::console('player {1} used built-in command "/{2}"', $login, $cmd);
            }
        }
    }
}
