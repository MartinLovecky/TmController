<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\App\WidgetBuilder;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\Fluent;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Infrastructure\Xml\Parser;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};
use Yuhzel\TmController\Services\{HttpClient, Server};

class ManiaKarma
{
    public Container $cfg;
    public array $karma = [];
    private int $retrytime = 0;
    private int $retrywait = 600;
    private int $totalPayout = 0;

    public function __construct(
        protected Fluent $fluent,
        protected Client $client,
        protected ChallengeService $challengeService,
        protected RecordService $recordService,
        protected PlayerService $playerService,
        protected HttpClient $httpClient,
        protected WidgetBuilder $widgetBuilder
    ) {
    }

    public function onSync(): void
    {
        // $agent = 'XAseco/1.17 mania-karma/2.0.1 TMF/' . Server::$build . ' php/'
        // . phpversion() . ' ' . php_uname('s') . '/' . php_uname('r') . ' ' . php_uname('m');
        $tmx = $this->challengeService->getTMX();
        $gbx = $this->challengeService->getGBX();

        $this->cfg = Container::fromJsonFile(Aseco::jsonFolderPath() . 'maniaKarma.json');
        Aseco::console('************************(ManiaKarma)*************************');
        Aseco::console('Karma 2.0.1 for TmController');
        Aseco::console(' => Set Server location to: {1}', $_ENV['dedi_nation']);
        Aseco::console(' => Trying to authenticate with central database: {1}', $this->cfg->get('api_auth'));

        $this->auth();

        $this->cfg->set('template', $this->loadTemplate());

        if (Aseco::$startupPhase === true) {
            $this->initializeKarmaForNewMap();
        }

        $this->calculateKarma(['global','local']);

        $this->cfg->set('widget.skeleton.race', $this->buildKarmaWidget($this->getGameMode()));
        $this->cfg->set('widget.skeleton.score', $this->buildKarmaWidget('score'));

        if ($this->retrytime === 0) {
            if ($this->getGameMode() === 'score') {
                $this->sendWidgetCombination(['skeleton_score', 'cups_values']); // mby provide player here [], $player
            } else {
                $this->sendWidgetCombination(['skeleton_race', 'cups_values']); // mby provide player here [], $player
            }

            foreach ($this->playerService->getAllPlayers() as $_ => $player) { // to get player loop trought collection
                $this->sendWidgetCombination(['player_marker'], $player);
            }

            $this->sendConnectionStatus(true, 'rounds');
        }

        // overrite message
        if ($this->cfg->get('karma_lottery.enabled')) {
            $karmaHelp = $this->cfg->get('messages.karma_help') . $this->cfg->get('messages.lottery_help');
            $splitHelp = str_replace('{br}', "\n", Aseco::formatColors($karmaHelp));
            $this->cfg->set('messages.karma_help', $splitHelp);
        }
    }

    public function onShutdown(): void
    {
        $this->storeKarmaVotes();
    }

    public function onChat(array $chat)
    {
        $login = $chat['login'];
        $text = trim($chat['text']);
        $player = $this->playerService->getPlayerByLogin($login);

        if (strpos($text, '/karma') === 0 && Aseco::isAnyAdmin($login)) {
            $this->handleKarmaCommand($player, $text);
        }
    }


    public function onPlayerConnect(Container $player): void // <- well player
    {
        if ($this->cfg->get('show_welcome')) {
            $message = Aseco::formatText(
                $this->cfg->get('messages.welcome'),
                "http://www.mania-karma.com/",
                $this->cfg->get('website')
            );
            $message = str_replace('{br}', "\n", $message);
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message), $player->get('Login')
            ]);
        }

        if (Aseco::isAnyAdmin($player->get('Login'))) {
            $this->uptodateCheck($player);

            if (!$this->cfg->get('import_done')) {
                $message = "{#server}> {#emotic}#################################################\n";
                $message .= "{#server}> {#emotic}start the export with the command /karma export\n";
                $message .= "{#server}> {#emotic}#################################################\n";
                $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message), $player->get('Login')
                ]);
            }
        }

        if ($this->cfg->get('karma_lottery.enabled') && $player->get('OnlineRights') === 3) {
            $player->set('ManiaKarma.LotteryPayout', 0);
        }

        $player->set('ManiaKarma.ReminderWindow', false);
        $player->set('ManiaKarma.FinishedMapCount', 0);

        $result = $this->recordService->getRecord($this->karma['data']['uid'])->get('Times');
        if (!$result->isEmpty()) {
            //TODO: find login in list if exist set ManiaKarma.FinishedMap = true
            dd('yay we have record: Karma 147');
        }

        if (!Aseco::isStartup()) {
            //WTF there is lot of bs in old code
            if ($this->getGameMode() === 'score') {
                $this->sendWidgetCombination(['skeleton_score', 'cups_values', 'player_marker'], $player);
            } else {
                $this->sendWidgetCombination(['skeleton_race', 'cups_values', 'player_marker'], $player);
            }
        }
    }

    //  idk how we will do this
    public function onPlayerDisconnect(Container $player)
    {
        $this->storeKarmaVotes();
        $this->closeReminderWindow($player);
    }

    // this is propably also player idk tho
    public function onPlayerManialinkPageAnswer($answer)
    {
        $player = $this->playerService->getPlayerByLogin($answer['login']);
        $action = (int) $answer['answer'];
        $voteMap = [18 => 3, 11 => 2, 10 => 1, 14 => -1, 15 => -2, 16 => -3];

        if (isset($voteMap[$action])) {
            $this->recordVote($player, $voteMap[$action]);
        }
    }

    // look we need player here
    public function onNewChallenge()
    {
        $this->closeReminderWindow();
        $this->sendWidgetCombination(['hide_all']);
        $this->storeKarmaVotes();

        foreach ($this->playerService->getAllPlayers() as $_ => $player) {
            $player->set('ManiaKarma.FinishedMapCount', 0);
        }
    }


    private function initializeKarmaForNewMap(): void
    {
        $tmx = $this->challengeService->getTMX();
        $gbx = $this->challengeService->getGBX();

        $this->karma = $this->createNewKarmaState();
        $this->karma['data'] = [
            'uid'    => !empty($tmx->uid) ? $tmx->uid : $gbx->UId,
            'id'     => $tmx->id ?? 0,
            'name'   => !empty($tmx->name) ? $tmx->name : Aseco::stripColors($gbx->name),
            'author' => !empty($tmx->author) ? $tmx->author : Aseco::stripColors($gbx->author),
            'env'    => !empty($tmx->envir) ? $tmx->envir : $gbx->envir,
        ];
        $this->karma['new']['players'] = $this->karma['local']['players'];
    }


    private function createNewKarmaState(): array
    {
        $state = [
            'data' => [],
            'global' => [
                'votes' => $this->getEmptyVotes(),
                'players' => [],
            ],
            'local' => $this->getLocalKarma($this->challengeService->getUid()),
        ];

        // Initialize player states without looping
        $logins = $this->playerService->getAllLogins();
        $playerVoteData = ['vote' => 0, 'previous' => 0];

        $state['global']['players'] = array_fill_keys($logins, $playerVoteData);

        if (Aseco::isStartup()) {
            $state['local']['players'] = array_fill_keys($logins, $playerVoteData);
        }

        return $state;
    }

    private function updateKarmaWidgetsForAllPlayers(): void
    {
        $gamemode = $this->getGameMode();
        $widgetType = ($gamemode === 'score') ? 'skeleton_score' : 'skeleton_race';

        $this->sendWidgetCombination([$widgetType, 'cups_values']);

        // Send player-specific widgets
        $this->playerService->eachPlayer(function (Container $player) use ($gamemode) {
            $this->sendWidgetCombination(['player_marker'], $player);
        });
    }

    //STUB onSync //player here also
    private function setEmptyKarma(bool $reset = false): array
    {
        $empty = [
        'data' => ['uid' => false],
        'global' => [
            'votes' => $this->getEmptyVotes(),
            'players' => [],
        ],
        ];

        if ($reset) {
            $empty['local'] = [
            'votes' => $this->getEmptyVotes(),
            'players' => [],
            ];
        } else {
            $empty['local'] = $this->getLocalKarma($this->challengeService->getUid());
        }

        foreach ($this->playerService->getAllPlayers() as $_ => $player) {
            $login = $player->get('Login');
            $playerVoteData = ['vote' => 0, 'previous' => 0];

            $empty['global']['players'][$login] = $playerVoteData;

            if ($reset) {
                $empty['local']['players'][$login] = $playerVoteData;
            }
        }

        return $empty;
    }

    private function getEmptyVotes(): array
    {
        $votes = [
        'karma' => 0,
        'total' => 0,
        ];

        foreach ($this->getVoteTypes() as $type) {
            $votes[$type] = [
            'percent' => 0,
            'count' => 0,
            ];
        }

        return $votes;
    }

    private function getLocalKarma(?string $mapId = null)
    {
        if (!$mapId) {
            return [
            'votes' => $this->getEmptyVotes(),
            'players' => [],
            ];
        }

        $this->initializeVoteCounts('local');

        //TODO Placeholder for future DB fetch
        $result = $this->fluent->query
        ->from('rs_karma')
        ->where('ChallengeId', $mapId)
        ->fetch();

        if ($result) {
            // Not used currently
            dd($result);
        }

        $this->calculateKarma(['local']);
    }

    private function initializeVoteCounts(string $location): void
    {
        foreach ($this->getVoteTypes() as $type) {
            $this->karma[$location]['votes'][$type] = [
            'percent' => 0,
            'count' => 0,
            ];
        }
    }

    private function calculateKarma(array $locations): void
    {
        foreach ($locations as $location) {
            $votes = &$this->karma[$location]['votes'];

            // Normalize negative counts to 0
            foreach ($this->getVoteTypes() as $type) {
                if ($votes[$type]['count'] < 0) {
                    $votes[$type]['count'] = 0;
                }
            }

            $totalVotes = array_sum(array_column($votes, 'count'));
            $totalVotes = $totalVotes > 0 ? $totalVotes : 0.0000000000001;

            // Calculate percentages
            foreach ($this->getVoteTypes() as $type) {
                $votes[$type]['percent'] = sprintf('%.2f', $votes[$type]['count'] / $totalVotes * 100);
            }

            // Calculate weighted karma
            if ($this->cfg->get('calculation_method') === 'rasp') {
                $votes['karma'] = floor(
                    ($votes['fantastic']['count'] * 3) +
                    ($votes['beautiful']['count'] * 2) +
                    ($votes['good']['count'] * 1) +
                    -($votes['bad']['count'] * 1) +
                    -($votes['poor']['count'] * 2) +
                    -($votes['waste']['count'] * 3)
                );
            } else {
                $good = ($votes['fantastic']['count'] * 100) +
                ($votes['beautiful']['count'] * 80) +
                ($votes['good']['count'] * 60);
                $bad = ($votes['bad']['count'] * 40) +
                   ($votes['poor']['count'] * 20); // waste * 0 is omitted

                $votes['karma'] = floor(($good + $bad) / $totalVotes);
            }

            $votes['total'] = (int) $totalVotes;
        }
    }

    private function buildKarmaWidget(string $gamemode): string
    {
        $voteButtons = [
        ['label' => '+++', 'id' => 18],
        ['label' => '++',  'id' => 11],
        ['label' => '+',   'id' => 10],
        ['label' => '-',   'id' => 14],
        ['label' => '--',  'id' => 15],
        ['label' => '---', 'id' => 16]
        ];

        $context = array_merge($this->getWidgetConfig($gamemode), [
        'uid' => $this->karma['data']['uid'] ?? $this->challengeService->getUid(),
        'env' => $this->karma['data']['env'] ?? 'Stadium',
        'game' => Server::$game,
        'gamemode' => $gamemode,
        'vote_buttons' => $voteButtons,
        ]);

        return $this->widgetBuilder->render('karma_widget', $context);
    }

    private function buildKarmaCupsValue(string $gamemode): string
    {
        $totalCups = 10;
        $cupOffset = [0.8, 0.85, 0.85, 0.875, 0.90, 0.925, 0.95, 0.975, 1.0, 1.025];

        $votes = $this->karma;

        $cups = [];
        $cup_gold_amount = $this->calculateGoldCupAmount($votes, $totalCups);

        for ($i = 0; $i < $totalCups; $i++) {
            $layer = sprintf("0.%02d", ($i + 1));
            $width = 1.1 + ($i / $totalCups) * $cupOffset[$i];
            $height = 1.5 + ($i / $totalCups) * $cupOffset[$i];
            $x = ($cupOffset[$i] * $i);
            $cups[] = [
            'x' => $x,
            'z' => $layer,
            'width' => $width,
            'height' => $height,
            'image' => ($i < $cup_gold_amount)
            ? $this->cfg->get('images.cup_gold')
            : $this->cfg->get('images.cup_silver')
            ];
        }

        $singular = $this->cfg->get('messages.karma_vote_singular');
        $plural = $this->cfg->get('messages.karma_vote_plural');

        return $this->widgetBuilder->render('karma_cups', array_merge(
            $this->getWidgetConfig($gamemode),
            [
            'cups' => $cups,
            'global_color' => 'FFFF',
            'global_karma' => '$0' . $votes['global']['votes']['karma'],
            'global_total' => number_format($votes['global']['votes']['total']),
            'global_label' => $votes['global']['votes']['total'] == 1 ? $singular : $plural,
            'local_color' => 'FFFF',
            'local_karma' => '$0' . $votes['local']['votes']['karma'],
            'local_total' => number_format($votes['local']['votes']['total']),
            'local_label' => $votes['local']['votes']['total'] == 1 ? $singular : $plural,
            ]
        ));
    }

    private function calculateGoldCupAmount(array $votes, int $totalCups): int
    {
        $method = $this->cfg->get('karma_calculation_method');

        if ($votes['global']['votes']['karma'] > 0) {
            if ($method === 'rasp') {
                $positive = $votes['global']['votes']['fantastic']['count']
                + $votes['global']['votes']['beautiful']['count']
                + $votes['global']['votes']['good']['count'];
                return (int)round($positive / $votes['global']['votes']['total'] * $totalCups);
            } else {
                return intval($votes['global']['votes']['karma'] / $totalCups);
            }
        } elseif ($votes['local']['votes']['karma'] > 0) {
            if ($method === 'rasp') {
                $positive = $votes['local']['votes']['fantastic']['count']
                + $votes['local']['votes']['beautiful']['count']
                + $votes['local']['votes']['good']['count'];
                return (int)round($positive / $votes['local']['votes']['total'] * $totalCups);
            } else {
                return intval($votes['local']['votes']['karma'] / $totalCups);
            }
        }

        return 0;
    }
    // yep player
    private function buildPlayerVoteMarker(?Container $player = null, string $gamemode): ?string
    {
        if (!$player instanceof Container) {
            return null;
        }

        $login = $player->get('Login');
        $playerVote = $this->karma['global']['players'][$login]['vote'] ?? 0;
        $finishedCount = $player->get('ManiaKarma.FinishedMapCount', 0);
        $xOffset = 1.83;
        $preset = [];
        $votes = [
        'fantastic' => 3,
        'beautiful' => 2,
        'good'      => 1,
        'bad'       => -1,
        'poor'      => -2,
        'waste'     => -3,
        ];

        foreach ($votes as $key => $value) {
            $preset[] = [
            'label'    => $key,
            'bgcolor'  => $playerVote === $value ? '9CFF' :
                ($playerVote === 0 && $finishedCount < 1 ? 'F70F' : '0000'),
            'action'   => $playerVote === $value ? 18 : 17,
            'pos_x'    => $xOffset,
            ];
            $xOffset += 2;
        }

        return $this->widgetBuilder->render('karma_vote_marker', array_merge(
            $this->getWidgetConfig($gamemode),
            ['votes' => $preset]
        ));
    }

    // player
    private function storeKarmaVotes(): void
    {
        $gbx = $this->challengeService->getGBX();

        if ($this->retrytime > 0 && time() >= $this->retrytime) {
            $this->onSync();
        }

        if ($this->retrytime === 0) {
            $pairs = [];
            foreach ($this->karma['new']['players'] as $login => $vote) {
                $pairs[] = urlencode($login) . '=' . $vote['vote'];
            }
            $authortime = 0;
            $authorscore = 0;

            if ($this->getGameMode() === 'stunts') {
                $authorscore = $this->challengeService->getGBX()->authorScore;
            } else {
                $authortime = $this->challengeService->getGBX()->authorTime;
            }

            $response = $this->httpClient->get($_ENV['KarmaAPI'], [
            'Action' => 'Vote',
            'login' => $this->cfg->get('account', $_ENV['server_login']),
            'authcode' => $_ENV['KarmaHash'],
            'uid' => $this->karma['data']['uid'],
            'map' => base64_encode($this->karma['data']['name']),
            'author' => urlencode($this->karma['data']['author']),
            'atime' => $authortime,
            'ascore' => $authorscore,
            'nblaps' => $gbx->nbLaps,
            'nbchecks' => $gbx->nbChecks,
            'mood' => $gbx->mood,
            'env' => $this->karma['data']['env'],
            'votes' => implode('|', $pairs),
            'tmx' => $this->karma['data']['id']
            ]);

            $output = Parser::fromXMLString($response);

            if ($output->get('status') !== 200) {
                Aseco::console("Storing votes failed with returncode {1}", $output->get('status'));
            } else {
                $this->retrytime = time() + $this->retrytime;
                $this->sendConnectionStatus(false, $this->getGameMode());
            }
        }
    }

    private function loadTemplate(): string
    {
        return $this->widgetBuilder->render('karma_template');
    }

    private function getWidgetConfig(string $gamemode): array
    {
        return [
        'pos_x' => $this->cfg->get("widget.{$gamemode}.pos_x"),
        'pos_y' => $this->cfg->get("widget.{$gamemode}.pos_y"),
        'title' => $this->cfg->get("widget_styles.{$gamemode}.title"),
        'icon_style' => $this->cfg->get("widget_styles.{$gamemode}.icon_style"),
        'icon_substyle' => $this->cfg->get("widget_styles.{$gamemode}.icon_substyle"),
        'background_style' => $this->cfg->get("widget_styles.{$gamemode}.background_style"),
        'background_substyle' => $this->cfg->get("widget_styles.{$gamemode}.background_substyle"),
        ];
    }

    private function sendWidgetCombination(array $widgets, ?Container $player = null): void
    {
        $xml = $this->widgetBuilder->render('karma_combination', [
        'widgets' => $widgets,
        'race' => $this->cfg->get('widget.skeleton.race'),
        'score' => $this->cfg->get('widget.skeleton.score'),
        'cups' => $this->buildKarmaCupsValue($this->getGameMode()),
        'marker' => $this->buildPlayerVoteMarker($player, $this->getGameMode())
        ]);

        if (!$player instanceof Container) {
            $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
        } else {
            $this->client->query('SendDisplayManialinkPageToLogin', [$player->get('Login'), $xml, 0, false]);
        }
    }

    private function sendConnectionStatus(bool $status, string $gamemode): void
    {
        $xml = $this->widgetBuilder->render('karma_connection', array_merge(
            $this->getWidgetConfig($gamemode),
            ['status' => $status]
        ));

        if (!$status) {
            $this->sendLoadingIndicator($status, $gamemode);
        }

        $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
    }

    private function sendLoadingIndicator(bool $status, string $gamemode): void
    {
        $xml = $this->widgetBuilder->render('karma_loading_indicator', array_merge(
            $this->getWidgetConfig($gamemode),
            ['status' => $status]
        ));

        $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
    }

    private function uptodateCheck(Container $player): void
    {
        $response = $this->httpClient->post('www.mania-karma.com/api/plugin-releases.xml');
        $output = Parser::fromXMLString($response);

        if (!version_compare($output->get('xaseco1xx'), '2.0.1', '>')) {
            $message = Aseco::formatText($this->cfg->get('messages.uptodate_ok'), '2.0.1');
            $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
            ]);
        } else {
            $message = Aseco::formatText(
                $this->cfg->get('messages.uptodate_new'),
                $output->get('xaseco1xx'),
                '$L[http://www.mania-karma.com/Downloads/]http://www.mania-karma.com/Downloads/$L'
            );
            $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
            ]);
        }
    }

    private function recordVote(Container $player, int $vote): void
    {
        $login = $player->get('Login');
        $previousVote = $this->karma['global']['players'][$login]['vote'] ?? 0;

        // Update global votes
        $this->updateVoteCount('global', $previousVote, $vote);

        // Update new votes for syncing
        $this->karma['new']['players'][$login] = $vote;
        $this->karma['global']['players'][$login] = ['vote' => $vote, 'previous' => $previousVote];

        $this->calculateKarma(['global']);
        $this->sendWidgetCombination(['cups_values', 'player_marker'], $player);
    }

    private function updateVoteCount(string $location, int $oldVote, int $newVote): void
    {
        $votes = &$this->karma[$location]['votes'];

        // Remove old vote
        $oldType = $this->getVoteTypeFromValue($oldVote);
        if ($oldType && isset($votes[$oldType])) {
            $votes[$oldType]['count'] = max(0, $votes[$oldType]['count'] - 1);
        }

        // Add new vote
        $newType = $this->getVoteTypeFromValue($newVote);
        if ($newType && isset($votes[$newType])) {
            $votes[$newType]['count']++;
        }
    }

    private function getVoteTypeFromValue(int $vote): ?string
    {
        $mapping = [
           3 => 'fantastic',
           2 => 'beautiful',
           1 => 'good',
           -1 => 'bad',
           -2 => 'poor',
           -3 => 'waste'
        ];

        return $mapping[$vote] ?? null;
    }

    private function handleKarmaCommand(Container $player, string $command): void
    {
        $parts = explode(' ', $command);
        $subCommand = $parts[1] ?? null;

        switch ($subCommand) {
            case 'export':
                $this->handleExportCommand($player);
                break;
            case 'help':
                $this->sendHelpMessage($player);
                break;
            default:
                //$this->sendCommandList($player);
        }
    }

    private function handleExportCommand(Container $player): void
    {
        $message = "Exporting karma data to central database...";
        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
        ]);

        $this->storeKarmaVotes();
        $this->cfg->set('import_done', true);
    }

    private function sendHelpMessage(Container $player): void
    {
        $help = $this->cfg->get('messages.karma_help');
        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors(str_replace('{br}', "\n", $help)),
            $player->get('Login')
        ]);
    }

    private function closeReminderWindow(?Container $player = null): void
    {
        $xml = $this->widgetBuilder->render('karma_manialink');

        if ($player instanceof Container) {
            if ($player->get('ManiaKarma.ReminderWindow') === true) {
                $player->set('ManiaKarma.ReminderWindow', false);
                $this->client->query('SendDisplayManialinkPageToLogin', [
                    $player->get('Login'),
                    $xml,
                    0,
                    false
                ]);
            }
        } else {
            foreach ($this->playerService->getAllPlayers() as $_ => $player) {
                if ($player->get('ManiaKarma.ReminderWindow') === true) {
                    $player->set('ManiaKarma.ReminderWindow', false);
                }
            }

            $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
        }
    }

    protected function getGameMode(): string
    {
        return match ($this->challengeService->getChallenge()->get('Options.GameMode')) {
            0 => 'rounds',
            1 => 'time_attack',
            2 => 'team',
            3 => 'laps',
            4 => 'stunts',
            5 => 'cup',
            7 => 'score',
            default => 'time_attack'
        };
    }

    protected function getVoteTypes(): array
    {
        return ['fantastic', 'beautiful', 'good', 'bad', 'poor', 'waste'];
    }

    protected function auth(): void
    {
        if (!isset($_ENV['KarmaHash'])) {
            $output = $this->httpClient->get($this->cfg->get('api_auth'), [
                'Action' => 'Auth',
                'login' => $_ENV['server_login'],
                'name' => Server::$name,
                'game' => Server::$game,
                'zone' => Server::$zone,
                'nation' => $_ENV['dedi_nation'],
            ]);

            $output = Parser::fromXMLString($output)->setReadonly();

            if ($output->get('status') !== 200) {
                $this->retrytime = (time() + $this->retrywait);
                $this->cfg
                ->set('authcode', false)
                ->set('api', false)
                ->set('import_done', true);  // Fake import done to do not ask a MasterAdmin to export
                Aseco::console(
                    ' => Connection failed with {1} for url {2}',
                    $output->get('status'),
                    $this->cfg->get('api_auth')
                );
                Aseco::console("Please check Auth variables");
                Aseco::console('**********************************************************');
            } elseif ($output->get('status') === 200) {
                $this->cfg->set('import_done', $output->get('import_done'));
                Aseco::updateEnvFile('KarmaAPI', $output->get('api_url'));
                Aseco::updateEnvFile('KarmaHash', $output->get('authcode'));
                Aseco::console(' => Successfully started with async communication.');
                Aseco::console(' => The API set the Request-URL to: {1}', $output->get('api_url'));
                Aseco::console('**********************************************************');
            }
        } else {
            Aseco::console(' => Successfully started with async communication.');
            Aseco::console('**********************************************************');
        }
    }
}
