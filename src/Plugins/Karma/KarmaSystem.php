<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\{Aseco, Sender, Server, WidgetBuilder};
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class KarmaSystem
{
    /** @var array<array{label:string,id:int}> */
    private const VOTE_BUTTONS = [
        ['label' => '+++', 'id' => 18],
        ['label' => '++',  'id' => 11],
        ['label' => '+',   'id' => 10],
        ['label' => '-',   'id' => 14],
        ['label' => '--',  'id' => 15],
        ['label' => '---', 'id' => 16]
    ];

    private string $file = 'karma' . DIRECTORY_SEPARATOR;
    private const CUP_OFFSETS = [0.8, 0.85, 0.85, 0.875, 0.90, 0.925, 0.95, 0.975, 1.0, 1.025];
    private const TOTAL_CUPS = 10;

    public function __construct(
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private Sender $sender,
        private KarmaState $karmaState,
        private WidgetBuilder $widgetBuilder
    ) {
    }

    // =================== Voting ===================
    public function recordVote(TmContainer $player, int $vote): void
    {
        $login = $player->get('Login');
        $nick  = $player->get('NickName');
        $previousVote = $this->karmaState->getPlayerVote($login) ?? 0;

        $this->karmaState->updateVoteCount('global', $previousVote, $vote);
        $this->karmaState->updatePlayerVote($login, $vote, $previousVote);
        $this->karmaState->calculateKarma(['global']);

        $average = $this->calculateAverage();

        // Send vote feedback to player
        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: "You voted {$vote} for this track. Avg Karma: {$average}",
            formatMode: Sender::FORMAT_COLORS
        );

        // Announce vote to all players
        $this->sender->sendChatMessageToAll(
            message: "$nick voted {$vote} for this track. Avg Karma: {$average}",
            formatMode: Sender::FORMAT_COLORS
        );

        $this->updateAllPlayerWidgets();
    }

    public function sendWelcomeMessage(TmContainer $player): void
    {
        // optional chat welcome (message comes from config, fallback text if missing)
        $welcome = $this->cfg('messages.karma_welcome') ?? 'Welcome — vote the map with the karma buttons!';
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: $welcome,
            formatMode: Sender::FORMAT_COLORS
        );

        // send the player's widgets (personal marker + global widgets)
        $this->sendWidgetCombination(['skeleton_race', 'cups_values', 'player_marker'], $player);
    }

    public function updatePlayerWidgets(TmContainer $player): void
    {
        $this->sendWidgetCombination(['skeleton_race', 'cups_values', 'player_marker'], $player);
    }

    public function closeReminderWindow(TmContainer $player): void
    {
        $this->renderAndSend($player, "{$this->file}combination", ['widgets' => []]);
    }

    private function calculateAverage(): float
    {
        $state = $this->karmaState->getKarmaState();
        $votes = array_values($state['global']['players'] ?? []);
        if (!$votes) {
            return 0.0;
        }

        $total = 0;
        $count = 0;
        foreach ($votes as $v) {
            if (!empty($v['vote'])) {
                $total += $v['vote'];
                $count++;
            }
        }
        return $count ? round($total / $count, 2) : 0.0;
    }

    // =================== Widget Rendering ===================
    public function sendInitialWidgets(): void
    {
        $this->sendWidgetCombination(['skeleton_race', 'cups_values']);

        $this->playerService->eachPlayer(fn(TmContainer $player) =>
            $this->sendWidgetCombination(['player_marker'], $player));

        $this->sendConnectionStatus(true, 'rounds');
    }

    public function updateAllPlayerWidgets(): void
    {
        $this->playerService->eachPlayer(fn(TmContainer $player) =>
            $this->sendWidgetCombination(['skeleton_race','cups_values','player_marker'], $player));
    }

    public function hideAllWidgets(): void
    {
        $this->playerService->eachPlayer(fn(TmContainer $player) =>
            $this->renderAndSend($player, "{$this->file}combination", ['widgets' => []]));
    }

    private function buildKarmaWidget(string $gameMode): string
    {
        $state = $this->karmaState->getKarmaState();
        $config = $this->getWidgetConfig($gameMode);
        return $this->widgetBuilder->render("{$this->file}widget", array_merge(
            $config,
            [
                'uid' => $state['data']['uid'] ?? '',
                'env' => $state['data']['env'] ?? 'Stadium',
                'game' => Server::$game,
                'gamemode' => $gameMode,
                'vote_buttons' => self::VOTE_BUTTONS
            ]
        ));
    }

    private function buildKarmaCupsValue(string $gameMode): string
    {
        $karmaState = $this->karmaState->getKarmaState();
        $goldCups = $this->calculateGoldCupAmount($karmaState);
        $config = $this->getWidgetConfig($gameMode);

        return $this->widgetBuilder->render("{$this->file}cups", array_merge(
            $config,
            [
                'cups' => $this->buildCupsArray($goldCups),
                'global_color' => 'FFFF',
                'global_karma' => '$0' . ($karmaState['global']['votes']['karma'] ?? 0),
                'global_total' => number_format($karmaState['global']['votes']['total'] ?? 0),
                'global_label' => $this->getVoteLabel($karmaState['global']['votes']['total'] ?? 0),
                'local_color' => 'FFFF',
                'local_karma' => '$0' . ($karmaState['local']['votes']['karma'] ?? 0),
                'local_total' => number_format($karmaState['local']['votes']['total'] ?? 0),
                'local_label' => $this->getVoteLabel($karmaState['local']['votes']['total'] ?? 0)
            ]
        ));
    }

    private function buildCupsArray(int $goldCups): array
    {
        $cups = [];
        for ($i = 0; $i < self::TOTAL_CUPS; $i++) {
            $dim = $this->getCupDimensions($i);
            $cups[] = [
                'x' => $dim['x'],
                'z' => $dim['z'],
                'width' => $dim['width'],
                'height' => $dim['height'],
                'image' => ($i < $goldCups) ? $this->cfg('images.cup_gold') : $this->cfg('images.cup_silver')
            ];
        }
        return $cups;
    }

    private function getCupDimensions(int $index): array
    {
        return [
            'width' => 1.1 + ($index / self::TOTAL_CUPS) * self::CUP_OFFSETS[$index],
            'height' => 1.5 + ($index / self::TOTAL_CUPS) * self::CUP_OFFSETS[$index],
            'x' => self::CUP_OFFSETS[$index] * $index,
            'z' => sprintf("0.%02d", $index + 1)
        ];
    }

    private function calculateGoldCupAmount(array $votes): int
    {
        $method = $this->cfg('karma_calculation_method');
        $globalKarma = $votes['global']['votes']['karma'] ?? 0;
        $localKarma = $votes['local']['votes']['karma'] ?? 0;

        if ($globalKarma > 0) {
            return max(0, $this->calculateCupsForKarma($votes['global'], $method));
        }
        if ($localKarma > 0) {
            return max(0, $this->calculateCupsForKarma($votes['local'], $method));
        }

        return 0;
    }

    private function calculateCupsForKarma(array $voteData, string $method): int
    {
        if ($method === 'rasp') {
            $positive = ($voteData['votes']['fantastic']['count'] ?? 0)
                      + ($voteData['votes']['beautiful']['count'] ?? 0)
                      + ($voteData['votes']['good']['count'] ?? 0);
            $total = $voteData['votes']['total'] ?? 1;
            return (int) round($positive / $total * self::TOTAL_CUPS);
        }
        return intval($voteData['votes']['karma'] / self::TOTAL_CUPS);
    }

    private function getVoteLabel(int $count): string
    {
        return $count === 1
            ? $this->cfg('messages.karma_vote_singular')
            : $this->cfg('messages.karma_vote_plural');
    }

    // =================== Widget Display ===================
    private function sendWidgetCombination(array $widgets, ?TmContainer $player = null): void
    {
        $gameMode = $this->challengeService->getGameMode();
        $context = [
            'widgets' => $widgets,
            'race' => $this->buildKarmaWidget($gameMode),
            'score' => $this->buildKarmaWidget('score'),
            'cups' => $this->buildKarmaCupsValue($gameMode),
            'marker' => $player ? $this->buildPlayerVoteMarker($player, $gameMode) : ''
        ];

        $this->renderAndSend($player ?? 'all', "{$this->file}combination", $context);
    }

    private function buildPlayerVoteMarker(TmContainer $player, string $gameMode): string
    {
        $login = $player->get('Login');
        $playerVote = $this->karmaState->getPlayerVote($login) ?? 0;
        $finishedCount = $player->get('ManiaKarma.FinishedMapCount', 0);
        $voteTypes = [3 => 'fantastic',2 => 'beautiful',1 => 'good',-1 => 'bad',-2 => 'poor',-3 => 'waste'];

        $presets = [];
        $xOffset = 1.83;
        foreach ($voteTypes as $value => $type) {
            $isActive = $playerVote === $value;
            $isInactive = $playerVote === 0 && $finishedCount < 1;

            $presets[] = [
                'label' => $type,
                'bgcolor' => $isActive ? '9CFF' : ($isInactive ? 'F70F' : '0000'),
                'action' => $isActive ? 18 : 17,
                'pos_x' => $xOffset
            ];
            $xOffset += 2;
        }

        return $this->widgetBuilder->render("{$this->file}vote_marker", array_merge(
            $this->getWidgetConfig($gameMode),
            ['votes' => $presets]
        ));
    }

    private function renderAndSend(TmContainer|string $target, string $template, array $context): void
    {
        if ($target instanceof TmContainer) {
            $this->sender->sendRenderToLogin(
                login: $target->get('Login'),
                template: $template,
                context: $context,
                formatMode: Sender::FORMAT_NONE
            );
        } else {
            $this->sender->sendRenderToAll(
                template: $template,
                context: $context,
                formatMode: Sender::FORMAT_NONE
            );
        }
    }

    private function getWidgetConfig(string $gameMode): array
    {
        return [
            'pos_x' => $this->cfg("widget.{$gameMode}.pos_x"),
            'pos_y' => $this->cfg("widget.{$gameMode}.pos_y"),
            'title' => $this->cfg("widget_styles.{$gameMode}.title"),
            'icon_style' => $this->cfg("widget_styles.{$gameMode}.icon_style"),
            'icon_substyle' => $this->cfg("widget_styles.{$gameMode}.icon_substyle"),
            'background_style' => $this->cfg("widget_styles.{$gameMode}.background_style"),
            'background_substyle' => $this->cfg("widget_styles.{$gameMode}.background_substyle"),
        ];
    }

    public function sendConnectionStatus(bool $status, string $gameMode): void
    {
        $file = $status ? "{$this->file}connection" : "{$this->file}loading_indicator";
        $context = array_merge(['status' => $status], $this->getWidgetConfig($gameMode));
        $this->sender->sendRenderToAll(template: $file, context: $context, formatMode: Sender::FORMAT_NONE);
    }

    private function cfg(string $path): mixed
    {
        return Aseco::getSetting($path, 'maniaKarma');
    }
}
