<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Controller;

use Yuhzel\TmController\App\Service\{Aseco, Log, Server};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Core\Enums\RestartMode;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};

class TmController
{
    public TmContainer $config;
    public ?TmContainer $bannedIps = null;
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
        Aseco::consoleText('[X8seco] Load settings from [{1}]', $conf);
        $this->config = TmContainer::fromJsonFile($conf, true);
    }

    private function readAdminOps(): void
    {
        $ops = Server::$jsonDir . 'adminops.json';
        Aseco::consoleText('[X8seco] Load admin/ops lists [{1}]', $ops);
        Aseco::$adminOps = TmContainer::fromJsonFile($ops);
    }

    private function readBannedIps(): void
    {
        $ipsFile = Server::$jsonDir . 'bannedips.json';
        $bannedIps = TmContainer::fromJsonFile($ipsFile);

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
        $this->playerService->eachPlayer(function (TmContainer $player) {
            $this->playerConnect($player);
            dd($player);
        });
    }

    private function playerConnect(TmContainer $player)
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

    private function playerDisconnect(TmContainer $player): void
    {
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

    private function beginRace(bool|TmContainer $challenge)
    {
        if (!$challenge) {
            $this->newChallenge($this->challengeService->getCurrentChallengeInfo());
        } else {
            $this->newChallenge($challenge);
        }
    }

    private function newChallenge(TmContainer $challenge): void
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

    private function buildRecordMessage(TmContainer $challenge): string
    {
        $curRecordTmContainer = $this->recordService->fetchSingle($this->challengeService->getUid());
        $gbx = $this->challengeService->getGBX();

        if (!$curRecordTmContainer->isEmpty()) {
            $firstRecord = $curRecordTmContainer->first();
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

    private function endRace(TmContainer $race): void
    {
        if ($race->get('4')) {
            $this->restarting = RestartMode::QUICK;

            if ($this->changingmode) {
                $this->restarting = RestartMode::FULL;
            } else {
                dd($race);
            }

            if ($this->restarting === RestartMode::FULL) {
                Aseco::consoleText('Restart Challenge (with ChatTime)');
            } else {
                Aseco::consoleText('Restart Challenge (instant)');
                $this->pm->callFunctions('onRestartChallenge', $race);
                return;
            }
        }

        Server::$gameState = 'score';
        if ($this->restarting === RestartMode::NONE) {
            Aseco::console('End Challenge');
        }

        $this->pm->callPluginFunction('chatCmd', 'trackrecs', null, 'after');

        $this->pm->callFunctions('onEndRace1', $race);
        $this->pm->callFunctions('onEndRace', $race);
    }

    private function executeCallbacks(): void
    {
        $this->client->readCallBack();

        while ($call = $this->client->popCBResponse()) {
            match ($call->get('methodName')) {
                'TrackMania.PlayerConnect'    => $this->callBackConnect($call),
                'TrackMania.PlayerDisconnect' => $this->callBackDisconnect($call),
                'TrackMania.PlayerChat'       => $this->playerChat($call),
                // this->pm->callPluginFunction('rasp', 'messageAnswer', $call)
                'TrackMania.PlayerServerMessageAnswer' => Log::debug('PlayerAnswer', $call->toArray(), 'PlayerAnswer'),
                'TrackMania.PlayerCheckpoint' => $this->pm->callFunctions('onCheckpoint', $call),
                'TrackMania.PlayerFinish'     => $this->playerFinish($call),
                // this->beginRound()
                'TrackMania.BeginRound' => Log::debug('BeginRound', $call->toArray(), 'BeginRound'),
                // $this->statusChanged()
                'TrackMania.StatusChanged' => Log::debug('StatusChanged', $call->toArray(), 'StatusChanged'),
                // $this->endRound()
                'TrackMania.EndRound' => Log::debug('EndRound', $call->toArray(), 'EndRound'),
                // beginRace
                'TrackMania.BeginChallenge' => Log::debug('beginRace', $call->toArray(), 'beginRace'),
                // $this->endRace($call)
                'TrackMania.EndChallenge' => Log::debug('EndChallenge', $call->toArray(), 'EndChallenge'),
                'TrackMania.PlayerManialinkPageAnswer' => $this->pm->callFunctions('onPlayerLink', $call),
                // onBillUpdated
                'TrackMania.BillUpdated' => Log::debug('BillUpdated', $call->toArray(), 'BillUpdated'),
                // onChallengeListModified
                'TrackMania.ChallengeListModified' => Log::debug('List', $call->toArray(), 'List'),
                // onPlayerInfoChanged
                'TrackMania.PlayerInfoChanged' => Log::debug('InfoC', $call->toArray(), 'InfoC'),
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
                default => Log::debug("Handle:? {$call->get('methodName')}")
            };
        }
    }

    private function callBackConnect(TmContainer $call): void
    {
        $player = $this->playerService->getPlayerInfo($call['chatType']);
        $this->playerService->addPlayer($player->get('Login'));
        $this->playerConnect($this->playerService->getPlayerByLogin($player->get('Login')));
    }

    private function callBackDisconnect(TmContainer $call): void
    {
        $player = $this->playerService->getPlayerByLogin($call['chatType']);
        $this->playerDisconnect($player);
        $this->playerService->removePlayer($player);
    }

    private function playerChat(TmContainer $call): void
    {
        $login = $call->get('login');
        $command = $call->get('message');

        $this->pm->callFunctions('onChat', $call);

        if ($command == '' || $command == '???') {
            Aseco::console(
                '{1} attempted to use chat command "{2}"',
                $login,
                $command
            );
        } elseif (str_starts_with($command, '/')) {
            $cmd = substr($command, 1);
            $params = explode(' ', $cmd, 2);
            $name = ucfirst(str_replace(['+', '-'], ['plus', 'dash'], $params[0]));
            // must be string dont change it
            $params[1] = isset($params[1]) ? trim($params[1]) : '';
            $player = $this->playerService->getPlayerByLogin($login);

            if ($player instanceof TmContainer) {
                Aseco::console(
                    'player {1} used chat command "/{2} {3}"',
                    $login,
                    $params[0],
                    $params[1]
                );
                $player->set('command.name', $name)->set('command.params', $params[1]);
                $this->pm->callFunctions("handleChatCommand", $player);
            } else {
                Aseco::console(
                    'player {1} attempted to use chat command "/{2} {3}"',
                    $login,
                    $params[0],
                    $params[1]
                );
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

    private function playerFinish(TmContainer $finish): void
    {
        Log::debug('PlayerFinish', $finish->toArray(), 'PlayerFinish');
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
}
