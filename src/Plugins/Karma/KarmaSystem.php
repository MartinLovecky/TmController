<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\Sender;
use Yuhzel\TmController\App\Service\Server;
use Yuhzel\TmController\App\Service\WidgetBuilder;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\KarmaService;
use Yuhzel\TmController\Repository\PlayerService;

class KarmaSystem
{
    /** @var array<array{label:string,id:int}> */
    public const VOTE_BUTTONS = [
        ['label' => '+++', 'id' => 18],
        ['label' => '++',  'id' => 11],
        ['label' => '+',   'id' => 10],
        ['label' => '-',   'id' => 14],
        ['label' => '--',  'id' => 15],
        ['label' => '---', 'id' => 16]
    ];
    private string $file = 'karma' . DIRECTORY_SEPARATOR;
    private const int TOTAL_CUPS = 10;
    private const CUP_OFFSETS = [0.8, 0.85, 0.85, 0.875, 0.90, 0.925, 0.95, 0.975, 1.0, 1.025];

    public function __construct(
        private KarmaConfig $karmaConfig,
        private KarmaService $karmaService,
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private Sender $sender,
        private WidgetBuilder $widgetBuilder,
    ) {
    }

    public function sendInitialWidgets(): void
    {
        $this->sendWidgetCombination(['skeleton_race', 'cups_values']);

        $this->playerService->eachPlayer(function (TmContainer $player) {
            $this->sendWidgetCombination(['player_marker'], $player);
        });

        $this->sendConnectionStatus(true, $this->challengeService->getGameMode());
    }

    public function sendWidgetCombination(array $widgets, ?TmContainer $player = null): void
    {
        $gameMode = $this->challengeService->getGameMode();

        $context = [];

        foreach ($widgets as $widget) {
            match ($widget) {
                'hide_all'      => $context['hide_ids'] = [91102, 91103, 91104, 91105, 91106, 91107],
                'hide_window'   => $context['hide_ids'] = [91102],
                'skeleton_race' => $context['race'] = $this->buildKarmaWidget($gameMode),
                'cups_values'   => $context['cups'] = $this->buildKarmaCupsValue($gameMode),
                'player_marker' => $context['marker'] = $player ? $this->buildPlayerVoteMarker($player, $gameMode) : '',
                default => null,
            };
        }
        $this->renderAndSend($player ?? 'all', "{$this->file}combination", $context);
    }

    public function sendWelcomeMessage(TmContainer $player): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: $this->karmaConfig->getMessage('welcome'),
            formatArgs: ["http://www.mania-karma.com/", "www.mania-karma.com"],
            formatMode: Sender::FORMAT_TEXT
        );
    }

    public function updatePlayerWidgets(TmContainer $player): void
    {
        $this->sendWidgetCombination(['skeleton_race', 'cups_values', 'player_marker'], $player);
    }

    private function buildKarmaWidget(string $gameMode): string
    {
        $gbx = $this->challengeService->getGBX();

        return $this->widgetBuilder->render("{$this->file}widget", array_merge(
            $this->karmaConfig->getWidget($gameMode),
            [
                'uid' => $gbx->UId,
                'env' => $gbx->envir,
                'game' => Server::$game,
                'gamemode' => $gameMode,
                'vote_buttons' => self::VOTE_BUTTONS
            ]
        ));
    }

    private function buildKarmaCupsValue(string $gameMode): string
    {
        $challengeId = $this->challengeService->getUid();
        $playerIds = $this->playerService->getAllLogins();

        $localVotes = [];
        $totalWeight = 0;
        foreach ($playerIds as $playerId) {
            $voteType = $this->karmaService->getVote($playerId, $challengeId);
            $karmaType = KarmaVoteType::from($voteType);
            if ($karmaType !== KarmaVoteType::NONE) {
                $dto = new KarmaVoteDTO([
                    'login' => $playerId,
                    'votes' => [],
                    'type' => $voteType,
                    'label' => $karmaType->label()
                ]);
                $dto->addVote($karmaType);
                $localVotes[] = $dto;
                $totalWeight += $karmaType->voteWeight();
            }
        }

        $goldCups = (int)($totalWeight / self::TOTAL_CUPS);

        return $this->widgetBuilder->render("{$this->file}widget", array_merge(
            $this->karmaConfig->getWidget($gameMode),
            [
                'cups' => $this->buildCupsArray($goldCups),
                'global_color' => 'FFFF',
                'global_karma' => '$0' . 0, // ($karmaState['global']['votes']['karma'] ?? 0)
                'global_total' => 0, //number_format($karmaState['global']['votes']['total'] ?? 0),
                'global_label' => 0, //$this->getVoteLabel($karmaState['global']['votes']['total'] ?? 0),
                'local_color' => 'FFFF',
                'local_karma' => '$0' . $totalWeight,
                'local_total' => count($playerIds),
                'local_label' => 'votes'
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
                'image' => ($i < $goldCups) ?
                    $this->karmaConfig->getImage('cup_gold')
                    : $this->karmaConfig->getImage('cup_silver')
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

    private function buildPlayerVoteMarker(TmContainer $player, string $gameMode): string
    {
        $login = $player->get('Login');
        $challengeId = $this->challengeService->getUid();

        // Current player vote
        $voteType = KarmaVoteType::from($this->karmaService->getVote($login, $challengeId));
        $finishedCount = $player->get('ManiaKarma.FinishedMapCount', 0);

        $presets = [];
        $xOffset = 1.83;

        foreach (KarmaVoteType::cases() as $case) {
            if ($case === KarmaVoteType::NONE || $case === KarmaVoteType::HELP) {
                continue; // skip "none" and "help" in marker display
            }

            $isActive   = $voteType === $case;
            $isInactive = $voteType === KarmaVoteType::NONE && $finishedCount < 1;

            $presets[] = [
                'label'   => $case->label(),
                'bgcolor' => $isActive ? '9CFF' : ($isInactive ? 'F70F' : '0000'),
                'action'  => $case->voteID(),
                'pos_x'   => $xOffset,
            ];

            $xOffset += 2;
        }

        return $this->widgetBuilder->render(
            "{$this->file}vote_marker",
            array_merge(
                $this->karmaConfig->getWidget($gameMode),
                ['votes' => $presets]
            )
        );
    }

    private function sendConnectionStatus(bool $status, string $gameMode): void
    {
        if ($gameMode === 'rounds') {
            return;
        }

        $template = $status ? "{$this->file}connection" : "{$this->file}loading_indicator";
        $context = array_merge(['status' => $status], $this->karmaConfig->getWidget($gameMode));

        $this->renderAndSend('all', $template, $context);
    }

    private function renderAndSend(
        TmContainer|string $target,
        string $template,
        array $context,
        array $formatArgs = [],
        string $formatMode = Sender::FORMAT_NONE
    ): void {
        if ($target instanceof TmContainer) {
            $this->sender->sendRenderToLogin(
                login: $target->get('Login'),
                template: $template,
                context: $context,
                formatArgs: $formatArgs,
                formatMode: $formatMode
            );
        } else {
            $this->sender->sendRenderToAll(
                template: $template,
                context: $context,
                formatArgs: $formatArgs,
                formatMode: $formatMode
            );
        }
    }
}
