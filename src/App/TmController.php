<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Server;
use Yuhzel\TmController\Core\Enums\RestartMode;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};

class TmController
{
    public Container $config;
    public ?Container $bannedIps = null;
    private RestartMode $restarting = RestartMode::NONE;
    private int $prevsecond = 0;
    private int $currsecond = 0;
    private int $pevStatus  = 0;
    private int $currStatus = 0;
    private bool $warmupPhase = false;
    private bool $changingmode = false;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected RecordService $recordService,
        protected PluginManager $pm,
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

        if ($this->currStatus === 100) {
            Aseco::consoleText('Waiting for the server to start a challenge');
        } else {
            $this->beginRace(false);
        }

        Aseco::$startupPhase = false;
        while (true) {
            $starttime = microtime(true);
            $this->executeCallbacks();
            //$this->pm->callFunctions('onMainLoop');

            $this->currsecond = time();
            if ($this->prevsecond !== $this->currsecond) {
                $this->prevsecond = $this->currsecond;
                $this->pm->callFunctions('onEverySecond');
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
        Aseco::consoleText('[X8seco] Load settings from [{1}]', $conf);
        $this->config = Container::fromJsonFile($conf, true);
    }

    private function readAdminOps(): void
    {
        $ops = Aseco::jsonFolderPath() . 'adminops.json';
        Aseco::consoleText('[X8seco] Load admin/ops lists [{1}]', $ops);
        Aseco::$adminOps = Container::fromJsonFile($ops);
    }

    private function readBannedIps(): void
    {
        $ipsFile = Aseco::jsonFolderPath() . 'bannedips.json';
        $bannedIps = Container::fromJsonFile($ipsFile);

        if (!$bannedIps->get('ban_list')->isEmpty()) {
            Aseco::consoleText('[X8seco] Load banned IPs list [{1}]', $ipsFile);
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
        $serverLogin = $this->client->query('GetSystemInfo')->get('ServerLogin');
        $this->server->setServerInfo($this->client->query('GetDetailedPlayerInfo', [$serverLogin]));
        $this->server->setVersion($this->client->query('GetVersion'));
        $this->server->setLadder($this->client->query('GetLadderServerLimits'));
        $this->server->setPackMask($this->client->query('GetServerPackMask')->get('value'));
        $this->server->setOptions($this->client->query('GetServerOptions'));

        $this->client->query('SendHideManialinkPage');

        $this->currStatus = $this->client->query('GetStatus')->get('Code');

        $this->playerService->syncFromServer();
        $this->pm->callFunctions('onSync');

        $this->playerService->eachPlayer(function (Container $player) {
            $this->playerConnect($player);
        });
    }

    private function playerConnect(Container $player)
    {
        if (!$this->bannedIps->isEmpty()) {
            foreach ($this->bannedIps->getIterator() as $_ => $ip) {
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

        $this->pm->callPluginFunction('chatCmd', 'trackrecs', $player->get('Login'), 'before');
        $this->pm->callFunctions('onPlayerConnect', $player);
    }

    private function disconnect(Container $player)
    {
        //NOTE: we cant rly remove from $player
        $playTime = time() - $player->get('created');
        Aseco::console(
            '>> player {1} left the game [{2} : {3}]',
            $player->get('Login'),
            $player->get('NickName'),
            Aseco::formatTimeH($playTime * 1000, false)
        );

        $this->pm->callFunctions('onPlayerDisconnect', $player);
    }

    private function sendHeader(): void
    {
        Aseco::consoleText('###############################################################################');
        Aseco::consoleText('  XASECO v 2.11.26 running on {1}:{2}', Server::$ip, Server::$port);
        Aseco::consoleText(
            '  Name   : {1} - {2}',
            Aseco::stripColors(Server::$name, false),
            Server::$serverLogin
        );
        Aseco::consoleText(
            '  Game   : {1} {2} - {3} - {4}',
            Server::$game,
            'United',
            Server::$packmask,
            'TimeAttack'
        );
        Aseco::consoleText('  Version: {1} / {2}', Server::$version, Server::$build);
        Aseco::consoleText('  Authors: Florian Schnell & Assembler Maniac');
        Aseco::consoleText('  Re-Authored: Xymph');
        Aseco::consoleText('  Remake: Yuhzel');
        Aseco::consoleText('###############################################################################');

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
        $this->changingmode = false;

        if ($this->restarting === RestartMode::NONE) {
            Aseco::consoleText('Begin Challenge');
        }

        if ($this->handleRestartState()) {
            return;
        }

        $this->pm->callFunctions('onNewChallenge', $this->challengeService);
        $message = $this->buildRecordMessage($challenge);
        $this->sendRecordMessage($message);

        $this->pm->callFunctions('onNewChallenge2', $this->challengeService->getGBX());
        $this->pm->callPluginFunction('chatCmd', 'trackrecs', null, 'before');
    }

    private function handleRestartState(): bool
    {
        if ($this->restarting !== RestartMode::NONE) {
            if ($this->restarting === RestartMode::FULL) {
                $this->restarting = RestartMode::NONE;
            } else { // QUICK
                $this->restarting = RestartMode::NONE;
                $this->pm->callFunctions(
                    'onRestartChallenge2',
                    $this->challengeService->getChallenge()
                );
                return true;
            }
        }
        return false;
    }

    private function buildRecordMessage(Container $challenge): string
    {
        $curRecordContainer = $this->recordService
        ->getRecord($this->challengeService->getUid())
        ->get('Times');

        $gbx = $this->challengeService->getGBX();

        if (!$curRecordContainer->isEmpty()) {
            $firstRecord = $curRecordContainer->first();
            $score = $this->formatScore($firstRecord->score);
            $playerName = $firstRecord->player->nickname ?? 'Unknown';

            Aseco::console(
                'current record on {1} is {2} and held by {3}',
                Aseco::stripColors($gbx->name, false),
                $score,
                Aseco::stripColors($playerName, false)
            );

            return Aseco::formatText(
                Aseco::getChatMessage('record_current'),
                Aseco::stripColors($gbx->name),
                $score,
                Aseco::stripColors($playerName)
            );
        }

        Aseco::console(
            'currently no record on {1}',
            Aseco::stripColors($challenge->get('Name'), false)
        );

        return Aseco::formatText(
            Aseco::getChatMessage('record_none'),
            Aseco::stripColors($challenge->get('Name'))
        );
    }

    private function sendRecordMessage(string $message): void
    {
        if (($this->config->get('show_recs_before') & 1) === 1) {
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
        }
    }

    private function formatScore(int $score): string
    {
        $mode = $this->challengeService->getGameMode();

        if ($mode === 'stunts') {
            return str_pad((string)$score, 5, ' ', STR_PAD_LEFT);
        }

        return Aseco::formatTime($score);
    }

    private function endRace($race)
    {
        dd($race);
    }

    private function executeCallbacks(): void
    {
        $this->client->readCallBack();
        $calls = $this->client->getCBResponses();

        if (empty($calls)) {
            return;
        }
        $ps = $this->playerService;
        /** @var ?Container $call*/
        while ($call = array_shift($calls)) {
            match ($call->get('methodName')) {
                'TrackMania.PlayerConnect' => $this->playerConnect($ps->getPlayerByLogin($call['login'])),
                'TrackMania.PlayerDisconnect' => $this->disconnect($ps->getPlayerByLogin($call['login'])),
                'TrackMania.PlayerChat' => $this->playerChat($call),
                'TrackMania.PlayerServerMessageAnswer' => $this->pm->callPluginFunction('rasp', 'messageAnswer', $call),
                'TrackMania.PlayerCheckpoint' => $this->pm->callFunctions('onCheckpoint', $call),
                'TrackMania.PlayerFinish' => $this->playerFinish($call),
                'TrackMania.BeginRound' => $this->beginRound(),
                'TrackMania.StatusChanged' => $this->statusChanged(),
                'TrackMania.EndRound' => $this->endRound(),
                'TrackMania.BeginChallenge' => dd($call), // beginRace
                'TrackMania.EndChallenge' => $this->endRace($call),
                'TrackMania.PlayerManialinkPageAnswer' => $this->pm->callFunctions('onPlayerLink', $call),
                'TrackMania.BillUpdated' => dd($call), // onBillUpdated
                'TrackMania.ChallengeListModified' => dd($call), // onChallengeListModified
                'TrackMania.PlayerInfoChanged' => dd($call), // this->playerInfoChanged
                'TrackMania.PlayerIncoherence' => dd($call), // onPlayerIncoherence
                'TrackMania.TunnelDataReceived' => dd($call), // onPlayerIncoherence
                'TrackMania.Echo' => dd($call), // onEcho
                'TrackMania.ManualFlowControlTransition' => dd($call), // onMfct
                'TrackMania.VoteUpdated' => dd($call), // onVoteUpdated
                default => dd("Handle:? {$call->get('methodName')}")
            };
        }
    }

    //NOTE: We dont register commnad for tmf
    private function playerChat(Container $call): void
    {
        $login = $call['login'];
        $command = $call['message'];

        $this->pm->callFunctions('onChat', $call);

        if ($command == '' || $command == '???') {
            Aseco::console(
                '{1} attempted to use chat command "{2}"',
                $call->get('1'),
                $command
            );
        } elseif (str_starts_with($command, '/')) {
            $cmd = substr($command, 1);
            $params = explode(' ', $cmd, 2);
            $name = ucfirst(str_replace(['+', '-'], ['plus', 'dash'], $params[0]));

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

                $this->pm->callFunctions("chat{$name}", $cmdCommand);
            } else {
                Aseco::console(
                    'player {1} attempted to use chat command "/{2} {3}"',
                    $login,
                    $params[0],
                    $params[1]
                );
                trigger_error("Player object for {$login} not found", E_USER_WARNING);
            }
        }
    }

    private function beginRound(): void
    {
        Aseco::consoleText('Begin Round');
        $this->pm->callFunctions('onBeginRound');
    }

    private function endRound(): void
    {
        Aseco::consoleText('End Round');
        $this->pm->callFunctions('onEndRound');
    }

    private function statusChanged(): void
    {
        $this->pevStatus = $this->currStatus;

        if ($this->currStatus === 3 || $this->currStatus === 5) {
            dd($this->client->query('GetWarmUp'));
        }

        if ($this->currStatus === 4) {
            $this->runningPlay();
        }

        $this->pm->callFunctions('onStatusChangeTo', $this->currStatus);
    }

    private function playerFinish(Container $finish): void
    {
        dd($finish);
        $date = date('Y/m/d;H:i:s');
        $uid = $finish['0']; // $this->challengeService->getUid()
        $login = $finish['login'];
        $score = $finish['2'];
        $new = false;

        $this->pm->callFunctions('onPlayerFinish1', $uid, $login, $date, $score, $new);
        $this->pm->callFunctions('onPlayerFinish', $uid, $login, $date, $score, $new);
    }

    private function runningPlay(): void
    {
        // request information about the new challenge
        // ... and callback to function newChallenge()
    }
}
