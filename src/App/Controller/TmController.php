<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Controller;

use Yuhzel\TmController\App\Service\{Aseco, Log, Server};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Core\Enums\RestartMode;
use Yuhzel\TmController\Core\Enums\Status;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};

class TmController
{
    public TmContainer $config;
    public ?TmContainer $bannedIps = null;
    private RestartMode $restarting = RestartMode::NONE;
    private Status $currStatus = Status::NONE;
    private int $prevsecond = 0;
    private int $currsecond = 0;
    private bool $warmupPhase = false;
    private bool $changingmode = false;

    public function __construct(
        private Client $client,
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private RecordService $recordService,
        private PluginManager $pm,
        private Server $server,
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

        if ($this->currStatus === Status::WAITING_FOR_CHALLENGE) {
            Aseco::consoleText('Waiting for the server to start a challenge');
        } else {
            $this->newChallenge();
        }

        Aseco::$startupPhase = false;
        while (true) {
            $starttime = microtime(true);
            $this->executeCallbacks();
            usleep(100_000);
            $this->pm->callFunctions('onMainLoop');

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
        $conf = Server::$jsonDir . 'config.json';
        Aseco::consoleText("Loaded settings from {$conf}");
        $this->config = TmContainer::fromJsonFile($conf, true);
    }

    private function readAdminOps(): void
    {
        $ops = Server::$jsonDir . 'adminops.json';
        Aseco::consoleText("Loaded admin/ops lists {$ops}");
        Aseco::$adminOps = TmContainer::fromJsonFile($ops);
    }

    private function readBannedIps(): void
    {
        $ipsFile = Server::$jsonDir . 'bannedips.json';
        $bannedIps = TmContainer::fromJsonFile($ipsFile);

        if (!$bannedIps->get('ban_list')->isEmpty()) {
            Aseco::consoleText("Loaded banned IPs list {$ipsFile}");
            $this->bannedIps = $bannedIps;
        } else {
            unset($bannedIps);
        }
    }

    private function connect(): void
    {
        $s = [
            'ip' => Server::$ip,
            'port' => Server::$port,
            'timeout' => Server::$timeout,
            'login' => Server::$login
        ];
        Aseco::console(
            "Trying to connect to TM dedicated server on {$s['ip']}:{$s['port']} timeout {$s['timeout']}s"
        );

        Aseco::console("Trying to authenticate with login {$s['login']}");

        $this->client->query('EnableCallbacks', [true]);
        $this->waitServerReady();

        Aseco::console('Connection established successfully!');
    }

    private function waitServerReady(): void
    {
        $status = $this->client->query('GetStatus');
        $this->currStatus = Status::tryFrom($status->get('Code')) ?? Status::NONE;

        if ($this->currStatus !== Status::RUNNING_PLAY) {
              Aseco::console("Waiting for dedicated server to reach status Running - Play...");
              Aseco::console("Status: {$status->get('Name')}");
              $timeout = 0;
              $lastStatusName = $status->get('Name');

            while ($this->currStatus !== Status::RUNNING_PLAY) {
                sleep(1);
                $newStatus = $this->client->query('GetStatus');
                $this->currStatus = Status::tryFrom($newStatus->get('Code')) ?? Status::NONE;

                if ($lastStatusName !== $newStatus->get('Name')) {
                    Aseco::console("Status: {$newStatus->get('Name')}");
                    $lastStatusName = $newStatus->get('Name');
                }

                if (Server::$timeout > $timeout++) {
                    trigger_error('Timed out while waiting for dedicated server!', E_USER_ERROR);
                }
            }
        }
    }

    private function serverSync(): void
    {
        // We doing this is bcs Server should not have any __constuct injection
        $serverLogin = $this->client->query('GetSystemInfo')->get('ServerLogin');
        $this->server->setServerInfo($this->client->query('GetDetailedPlayerInfo', [$serverLogin]));
        $this->server->setVersion($this->client->query('GetVersion'));
        $this->server->setLadder($this->client->query('GetLadderServerLimits'));
        $this->server->setPackMask($this->client->query('GetServerPackMask')->get('value'));
        $this->server->setOptions($this->client->query('GetServerOptions'));

        $this->client->query('SendHideManialinkPage');
        $status = $this->client->query('GetStatus');
        $this->currStatus = Status::tryFrom($status->get('Code')) ?? Status::NONE;

        $this->playerService->syncFromServer();
        $this->pm->callFunctions('onSync');

        // if you need admin for 3rd party
        // $virtualAdmin = $this->remoteAdmin->createVirtualAdmin('login');
        // $playerService->addPlayer($virtualAdmin->get('Login'), true);

        $masterAdmin = $this->playerService->getPlayerList(1)[0];
        $this->playerService->addPlayer($masterAdmin->get('Login'), true);
    }

    private function playerConnect(TmContainer $player): void
    {
        if (!$this->bannedIps->isEmpty()) {
            foreach ($this->bannedIps->getIterator() as $_ => $ip) {
                if ($ip === $player->get('IPAddress')) {
                    return;
                }
            }
        }

        Aseco::console("{$player->get('NickName')} joined the game from {$player->get('Path')}");

        $version = str_replace(')', '', preg_replace('/.*\(/', '', $player->get('ClientVersion')));
        $message = Aseco::formatText(
            Aseco::getChatMessage('welcome'),
            Aseco::stripColors($player->get('Login')),
            Server::$name,
            $version
        );
        $message = preg_replace('/X8SECO.+' . $version . '/', '$l[http://www.gamers.org/tmf]$0$l', $message);
        $message = str_replace("\n" . "\n", "\n", $message);

        $this->client->query('ChatSendServerMessageToLogin', [$message, $player->get('Login')]);

        $this->pm->callPluginFunction('chatCmd', 'trackrecs', $player->get('Login'), 'before');
        $this->pm->callFunctions('onPlayerConnect', $player);
    }

    private function playerDisconnect(string $login): void
    {
        //TODO : $player object is invalid at this point we want have
        // time that he played also we can store it in db
        $playTime = Aseco::formatTimeH(time() * 1000, false);
        Aseco::console(">> {$login} left the game after {$playTime}");

        $this->pm->callFunctions('onPlayerDisconnect', $login);
        $this->playerService->removePlayer($login);
    }

    private function sendHeader(): void
    {
        Aseco::consoleText('###############################################################################');
        Aseco::consoleText('  X8SECO v 3.0.0 running on {1}:{2}', Server::$ip, Server::$port);
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

    private function newChallenge(): void
    {
        $this->changingmode = false;

        if ($this->restarting === RestartMode::NONE) {
            Aseco::consoleText('Begin Challenge');
        }

        if ($this->handleRestartState()) {
            return;
        }

        $this->pm->callFunctions('onNewChallenge');

        $message = $this->buildRecordMessage();
        $this->sendRecordMessage($message);

        $this->pm->callFunctions('onNewChallenge2');
        $this->pm->callPluginFunction('chatCmd', 'trackrecs', null, 'before');
    }

    private function handleRestartState(): bool
    {
        if ($this->restarting !== RestartMode::NONE) {
            if ($this->restarting === RestartMode::FULL) {
                $this->restarting = RestartMode::NONE;
            } else { // QUICK
                $this->restarting = RestartMode::NONE;
                $this->pm->callFunctions('onRestartChallenge');
                return true;
            }
        }
        return false;
    }

    private function buildRecordMessage(): string
    {
        $currRecord = $this->recordService->fetchSingle($this->challengeService->getUid());
        $gbx = $this->challengeService->getGBX();

        if (!$currRecord->isEmpty()) {
            $firstRecord = $currRecord->first();
            $score = Aseco::formatTime($firstRecord->score);
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

        Aseco::console('currently no record on {1}', Aseco::stripColors($gbx->name, false));

        return Aseco::formatText(
            Aseco::getChatMessage('record_none'),
            Aseco::stripColors($gbx->name)
        );
    }

    private function sendRecordMessage(string $message): void
    {
        if (($this->config->get('show_recs_before') & 1) === 1) {
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
        }
    }

    private function endRace(TmContainer $call): void
    {
        if ($call->get('reservedFlag')) {
            $this->restarting = RestartMode::QUICK;

            if ($this->changingmode) {
                $this->restarting = RestartMode::FULL;
            } else {
                // can have mutiple infos
                foreach ($call->get('playerInfo') as $info) {
                    if ($info['BestTime'] > 0 || $info['Score'] > 0) {
                        $this->restarting = RestartMode::FULL;
                        break;
                    }
                }
            }

            if ($this->restarting === RestartMode::FULL) {
                Aseco::consoleText('Restart Challenge (with ChatTime)');
            } else {
                Aseco::consoleText('Restart Challenge (instant)');
                $this->pm->callFunctions('onRestartChallenge');
                return;
            }
        }

        Server::$gameState = 'score';
        if ($this->restarting === RestartMode::NONE) {
            Aseco::console('End Challenge');
        }
        // REVIEW thisis bit sus
        $this->pm->callPluginFunction('chatCmd', 'trackrecs', null, 'after');

        $this->pm->callFunctions('onEndRace1', $call);
        $this->pm->callFunctions('onEndRace', $call);
    }

    private function executeCallbacks(): void
    {
        $this->client->readCallBack();

        while ($call = $this->client->popCBResponse()) {
            match ($call->get('methodName')) {
                'TrackMania.PlayerConnect'    => $this->callBackConnect($call->get('login')),
                'TrackMania.PlayerDisconnect' => $this->playerDisconnect($call->get('login')),
                'TrackMania.PlayerChat'       => $this->playerChat($call),
                // this->pm->callPluginFunction('rasp', 'messageAnswer', $call)
                'TrackMania.PlayerCheckpoint' => $this->pm->callFunctions('onCheckpoint', $call),
                'TrackMania.PlayerFinish'     => $this->playerFinish($call),
                'TrackMania.BeginRound'       => $this->beginRound(),
                'TrackMania.StatusChanged'    => $this->gameStatusChanged($call->get('statusCode')),
                'TrackMania.EndRound'         => $this->endRound(),
                'TrackMania.BeginChallenge'   => $this->newChallenge(),
                'TrackMania.EndChallenge'     => $this->endRace($call->get('challengeInfo')),
                'TrackMania.PlayerServerMessageAnswer' => Log::debug('PlayerAnswer', $call->toArray(), 'PlayerAnswer'),
                'TrackMania.PlayerManialinkPageAnswer' => $this->pm->callFunctions('onPlayerLink', $call),
                // onBillUpdated
                'TrackMania.BillUpdated' => Log::debug('BillUpdated', $call->toArray(), 'BillUpdated'),
                // onChallengeListModified
                'TrackMania.ChallengeListModified' => Log::debug('List', $call->toArray(), 'List'),
                'TrackMania.PlayerInfoChanged' => null,//$this->pm->callFunctions('onPlayerInfoChanged', $call),
                // onPlayerIncoherence
                'TrackMania.PlayerIncoherence' => Log::debug('PlayerI', $call->toArray(), 'PlayerI'),
                // onTunnelDataReceived
                'TrackMania.TunnelDataReceived' => Log::debug('Tunenel', $call->toArray(), 'Tunenel'),
                // echo
                'TrackMania.Echo' => Log::debug('Echo', $call->toArray(), 'Echo'),
                // ManualFlowControlTransition
                'TrackMania.ManualFlowControlTransition' => Log::debug('FlowControl', $call->toArray(), 'FlowControl'),
                // onVoteUpdated
                'TrackMania.VoteUpdated' => Log::debug('VoteUpdated', $call->toArray(), 'VoteUpdated'),
                default => null
            };
        }
    }

    private function callBackConnect(string $login): void
    {
        $playerInfo = $this->playerService->getPlayerInfo($login);
        $this->playerService->addPlayer($playerInfo->get('Login'));
        $player = $this->playerService->getPlayerByLogin($playerInfo->get('Login'));
        $this->playerConnect($player);
    }

    private function playerChat(TmContainer $call): void
    {
        $login = $call->get('login');
        $command = $call->get('message');

        if (str_starts_with($command, '/')) {
            $cmd = preg_split('/\s+/', substr($command, 1), 3);
            $cmdName = str_replace(['+', '-'], ['plus', 'minus'], $cmd[0]);
            $cmdParam = $cmd[1] ?? '';
            $cmdArg = $cmd[2] ?? '';

            $player = $this->playerService->getPlayerByLogin($login);

            if ($player instanceof TmContainer) {
                Aseco::console("{$player->get('NickName')} used chat command /{$cmdName} {$cmdParam}");
                $player->setMultiple([
                    'command.name' => $cmdName,
                    'command.param' => $cmdParam,
                    'command.arg' => $cmdArg
                ]);
                $this->pm->callFunctions("handleChatCommand", $player);
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

    private function gameStatusChanged(int $status): void
    {
        $this->currStatus = Status::tryFrom($status) ?? Status::NONE;

        match ($this->currStatus) {
            Status::RUNNING_SYNC, Status::FINISH => $this->warmup(),
            Status::RUNNING_PLAY => $this->runningPlay(),
            default => null,
        };

        $this->pm->callFunctions('onStatusChangeTo', $this->currStatus);
    }

    private function playerFinish(TmContainer $finish): void
    {
        $date = date('Y/m/d;H:i:s');
        $uid = $this->challengeService->getUid();
        $login = $finish->get('login');
        $score = null;
        $new = false;

        //$this->pm->callFunctions('onPlayerFinish1', $uid, $login, $date, $score, $new);
        //$this->pm->callFunctions('onPlayerFinish', $uid, $login, $date, $score, $new);
    }

    private function runningPlay(): void
    {
        // request information about the new challenge
        // ... and callback to function newChallenge()
    }

    private function warmup(): void
    {
        $this->warmupPhase = $this->client->query('GetWarmUp')->get('value');
    }
}
