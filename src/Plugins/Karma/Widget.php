<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Services\Server;
use Yuhzel\TmController\App\Service\{Aseco, WidgetBuilder};
use Yuhzel\TmController\Plugins\Karma\State;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class Widget
{
    private const VOTE_BUTTONS = [
        ['label' => '+++', 'id' => 18],
        ['label' => '++',  'id' => 11],
        ['label' => '+',   'id' => 10],
        ['label' => '-',   'id' => 14],
        ['label' => '--',  'id' => 15],
        ['label' => '---', 'id' => 16]
    ];
    private string $file = '';
    private const CUP_OFFSETS = [0.8, 0.85, 0.85, 0.875, 0.90, 0.925, 0.95, 0.975, 1.0, 1.025];
    private const TOTAL_CUPS = 10;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected State $stateService,
        protected WidgetBuilder $widgetBuilder,
    ) {
        $this->file = 'karma' . DIRECTORY_SEPARATOR;
    }

    public function getTemplate(): string
    {
        return $this->widgetBuilder->render("{$this->file}template");
    }

    public function sendInitialWidgets(): void
    {
        $widgetKey = ($this->challengeService->getGameMode() === 'score') ? 'skeleton_score' : 'skeleton_race';

        $this->sendWidgetCombination([$widgetKey, 'cups_values']);

        $this->playerService->eachPlayer(function (TmContainer $player) {
            $this->sendWidgetCombination(['player_marker'], $player);
        });

        $this->sendConnectionStatus(true, 'rounds');
    }

    public function updatePlayerWidgets(TmContainer $player): void
    {
        $widgetKey = ($this->challengeService->getGameMode() === 'score') ? 'skeleton_score' : 'skeleton_race';
        $this->sendWidgetCombination([$widgetKey, 'cups_values', 'player_marker'], $player);
    }

    public function sendWelcomeMessage(TmContainer $player): void
    {
        if (!$this->cfg()->get('show_welcome')) {
            return;
        }

        $message = Aseco::formatText(
            $this->cfg()->get('messages.welcome'),
            "http://www.mania-karma.com/",
            $this->cfg()->get('website')
        );
        $message = str_replace('{br}', "\n", $message);

        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors($message),
            $player->get('Login')
        ]);
    }

    public function closeReminderWindow(?TmContainer $player = null): void
    {
        $xml = $this->widgetBuilder->render("{$this->file}manialink");

        if ($player) {
            if ($player->get('ManiaKarma.ReminderWindow')) {
                $player->set('ManiaKarma.ReminderWindow', false);
                $this->client->query('SendDisplayManialinkPageToLogin', [
                    $player->get('Login'),
                    $xml,
                    0,
                    false
                ]);
            }
        } else {
            $this->playerService->eachPlayer(function (TmContainer $player) {
                $player->set('ManiaKarma.ReminderWindow', false);
            });
            $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
        }
    }

    public function hideAllWidgets(): void
    {
        $this->sendWidgetCombination(['hide_all']);
    }

    private function buildKarmaWidget(string $gameMode): string
    {
        return $this->widgetBuilder->render("{$this->file}widget", array_merge(
            $this->getWidgetConfig($gameMode),
            [
                'uid' => $this->stateService->getKarmaState()['data']['uid'] ?? '',
                'env' => $this->stateService->getKarmaState()['data']['env'] ?? 'Stadium',
                'game' => Server::$game,
                'gamemode' => $gameMode,
                'vote_buttons' => self::VOTE_BUTTONS
            ]
        ));
    }

    private function buildKarmaCupsValue(string $gameMode): string
    {
        $karmaState = $this->stateService->getKarmaState();
        $votes = $karmaState;
        $goldCups = $this->calculateGoldCupAmount($votes);

        return $this->widgetBuilder->render("{$this->file}cups", array_merge(
            $this->getWidgetConfig($gameMode),
            [
                'cups' => $this->buildCupsArray($goldCups),
                'global_color' => 'FFFF',
                'global_karma' => '$0' . $votes['global']['votes']['karma'],
                'global_total' => number_format($votes['global']['votes']['total']),
                'global_label' => $this->getVoteLabel($votes['global']['votes']['total']),
                'local_color' => 'FFFF',
                'local_karma' => '$0' . $votes['local']['votes']['karma'],
                'local_total' => number_format($votes['local']['votes']['total']),
                'local_label' => $this->getVoteLabel($votes['local']['votes']['total'])
            ]
        ));
    }

    private function buildCupsArray(int $goldCups): array
    {
        $cups = [];
        for ($i = 0; $i < self::TOTAL_CUPS; $i++) {
            $layer = sprintf("0.%02d", ($i + 1));
            $width = 1.1 + ($i / self::TOTAL_CUPS) * self::CUP_OFFSETS[$i];
            $height = 1.5 + ($i / self::TOTAL_CUPS) * self::CUP_OFFSETS[$i];
            $x = (self::CUP_OFFSETS[$i] * $i);

            $cups[] = [
                'x' => $x,
                'z' => $layer,
                'width' => $width,
                'height' => $height,
                'image' => ($i < $goldCups)
                    ? $this->cfg()->get('images.cup_gold')
                    : $this->cfg()->get('images.cup_silver')
            ];
        }
        return $cups;
    }

    private function calculateGoldCupAmount(array $votes): int
    {
        $method = $this->cfg()->get('karma_calculation_method');
        $globalKarma = $votes['global']['votes']['karma'];
        $localKarma = $votes['local']['votes']['karma'];

        if ($globalKarma > 0) {
            return $this->calculateCupsForKarma($votes['global'], $method);
        } elseif ($localKarma > 0) {
            return $this->calculateCupsForKarma($votes['local'], $method);
        }

        return 0;
    }

    private function calculateCupsForKarma(array $voteData, string $method): int
    {
        if ($method === 'rasp') {
            $positive = $voteData['votes']['fantastic']['count']
                + $voteData['votes']['beautiful']['count']
                + $voteData['votes']['good']['count'];
            return (int) round($positive / $voteData['votes']['total'] * self::TOTAL_CUPS);
        }
        return intval($voteData['votes']['karma'] / self::TOTAL_CUPS);
    }

    private function getVoteLabel(int $count): string
    {
        return $count === 1
            ? $this->cfg()->get('messages.karma_vote_singular')
            : $this->cfg()->get('messages.karma_vote_plural');
    }

    private function buildPlayerVoteMarker(TmContainer $player, string $gameMode): string
    {
        $login = $player->get('Login');
        $playerVote = $this->stateService->getPlayerVote($login) ?? 0;
        $finishedCount = $player->get('ManiaKarma.FinishedMapCount', 0);
        $voteTypes = [
            3 => 'fantastic',
            2 => 'beautiful',
            1 => 'good',
            -1 => 'bad',
            -2 => 'poor',
            -3 => 'waste'
        ];

        $presets = [];
        $xOffset = 1.83;

        foreach ($voteTypes as $value => $type) {
            $isActive = $playerVote === $value;
            $isInactive = $playerVote === 0 && $finishedCount < 1;

            $presets[] = [
                'label'    => $type,
                'bgcolor'  => $isActive ? '9CFF' : ($isInactive ? 'F70F' : '0000'),
                'action'   => $isActive ? 18 : 17,
                'pos_x'    => $xOffset,
            ];
            $xOffset += 2;
        }

        return $this->widgetBuilder->render("{$this->file}vote_marker", array_merge(
            $this->getWidgetConfig($gameMode),
            ['votes' => $presets]
        ));
    }

    private function getWidgetConfig(string $gameMode): array
    {
        return [
            'pos_x' => $this->cfg()->get("widget.{$gameMode}.pos_x"),
            'pos_y' => $this->cfg()->get("widget.{$gameMode}.pos_y"),
            'title' => $this->cfg()->get("widget_styles.{$gameMode}.title"),
            'icon_style' => $this->cfg()->get("widget_styles.{$gameMode}.icon_style"),
            'icon_substyle' => $this->cfg()->get("widget_styles.{$gameMode}.icon_substyle"),
            'background_style' => $this->cfg()->get("widget_styles.{$gameMode}.background_style"),
            'background_substyle' => $this->cfg()->get("widget_styles.{$gameMode}.background_substyle"),
        ];
    }

    public function sendWidgetCombination(
        array $widgets,
        ?TmContainer $player = null
    ): void {
        $gameMode = $this->challengeService->getGameMode();
        $playerMarker = $player ? $this->buildPlayerVoteMarker($player, $gameMode) : '';

        $xml = $this->widgetBuilder->render("{$this->file}combination", [
            'widgets' => $widgets,
            'race' => $this->buildKarmaWidget($gameMode),
            'score' => $this->buildKarmaWidget('score'),
            'cups' => $this->buildKarmaCupsValue($gameMode),
            'marker' => $playerMarker
        ]);

        if ($player) {
            $this->client->query('SendDisplayManialinkPageToLogin', [
                $player->get('Login'),
                $xml,
                0,
                false
            ]);
        } else {
            $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
        }
    }

    public function sendConnectionStatus(bool $status, string $gameMode): void
    {
        $xml = $this->widgetBuilder->render("{$this->file}connection", array_merge(
            $this->getWidgetConfig($gameMode),
            ['status' => $status]
        ));

        if (!$status) {
            $this->sendLoadingIndicator($status, $gameMode);
        }

        $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
    }

    private function sendLoadingIndicator(bool $status, string $gameMode): void
    {
        $xml = $this->widgetBuilder->render("{$this->file}loading_indicator", array_merge(
            $this->getWidgetConfig($gameMode),
            ['status' => $status]
        ));

        $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
    }

    private function cfg(): TmContainer
    {
        return TmContainer::fromJsonFile(Server::$jsonDir . 'maniaKarma.json');
    }
}
