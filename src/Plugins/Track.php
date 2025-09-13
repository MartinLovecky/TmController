<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Aseco, Sender, Server};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\ChallengeService;

class Track
{
    public int $replays_counter = 0;
    private int $replays_total = 0;

    public function __construct(
        private ChallengeService $challengeService,
        private Sender $sender,
    ) {
    }

    public function onSync(): void
    {
        $this->replays_counter = 0;
        $this->replays_total = 0;
        Server::$startTime = time();
    }

    public function onNewChallenge(): void
    {
        $challenge = $this->challengeService->getChallenge();
        $formatArgs = $this->getTrackInfoMessage($challenge);

        $this->sender->sendChatMessageToAll(
            message: Aseco::getChatMessage('current_track'),
            formatArgs: $formatArgs
        );
    }

    public function onNewChallenge2(): void
    {
        $challenge = $this->challengeService->getChallenge();
        $challenge->set('startTime', time());

        if ($this->replays_total === 0) {
            Server::$startTime = time();
        }
    }

    public function onEndRace(): void
    {
        $gameMode = $this->challengeService->getGameMode();
        if ($gameMode === 'time_attack' || $gameMode === 'stunts') {
            return;
        }

        $challenge = $this->challengeService->getChallenge();
        $name = $this->formatTrackName($challenge);

        $playTime = $this->formatTime($this->timePlaying());
        $totalTime = $this->formatTime(time() - Server::$startTime);

        if ($this->replays_total === 0) {
            Aseco::console('track [{1}] finished after: {2}', $name, $playTime);
            $this->sender->sendChatMessageToAll(
                message: Aseco::getChatMessage('playtime_finish'),
                formatArgs: [$name, $playTime]
            );
        } else {
            Aseco::console(
                'track [{1}] finished after: {2} ({3} replay{4}, total: {5})',
                $name,
                $playTime,
                $this->replays_total,
                ($this->replays_total === 1 ? '' : 's'),
                $totalTime
            );
            $this->sender->sendChatMessageToAll(
                message: Aseco::getChatMessage('playtime_replay'),
                formatArgs: [
                    $this->replays_total,
                    ($this->replays_total === 1 ? '' : 's'),
                    $totalTime
                ]
            );
        }
    }

    public function handleChatCommand(TmContainer $player): void
    {
        if ($player->get('command.name') !== 'track') {
            return;
        }

        $login = $player->get('Login');

        match (strtolower($player->get('command.params'))) {
            'playtime' => $this->playTime($login),
            'time' => $this->showTime($login),
            'track' => $this->sendTrackInfoToLogin($login),
            default => null,
        };
    }

    public function timePlaying(): int
    {
        return time() - $this->challengeService->getChallenge()->get('startTime');
    }

    private function playTime(string $login): void
    {
        $name = $this->formatTrackName($this->challengeService->getChallenge());
        $playTime = $this->formatTime($this->timePlaying());
        $totalTime = $this->formatTime(time() - Server::$startTime);

        if ($this->replays_total > 0) {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('playtime_replay'),
                formatArgs: [
                    $this->replays_total,
                    ($this->replays_total === 1 ? '' : 's'),
                    $totalTime
                ]
            );
        } else {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('playtime'),
                formatArgs: [$name, $playTime]
            );
        }
    }

    private function showTime(string $login): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: Aseco::getChatMessage('time'),
            formatArgs: [
                date('H:i:s T'),
                date('Y/M/d')
            ]
        );
    }

    private function sendTrackInfoToLogin(string $login): void
    {
        $challenge = $this->challengeService->getChallenge();
        $formatArgs = $this->getTrackInfoMessage($challenge);

        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: Aseco::getChatMessage('current_track'),
            formatArgs: $formatArgs
        );
    }

    private function getTrackInfoMessage(TmContainer $challenge): array
    {
        return [
            $this->formatTrackName($challenge),
            $challenge->get('Author'),
            Aseco::formatTime($challenge->get('AuthorTime')),
            Aseco::formatTime($challenge->get('GoldTime')),
            Aseco::formatTime($challenge->get('SilverTime')),
            Aseco::formatTime($challenge->get('BronzeTime')),
            $challenge->get('CopperPrice')
        ];
    }

    private function formatTrackName($challenge): string
    {
        $tmx = $this->challengeService->getTMX();
        $name = Aseco::stripColors($challenge->get('Name'));

        if (!empty($tmx->name)) {
            $name = "\$l[http://{$tmx->prefix}.tm-exchange.com/main.aspx?";
            $name .= "action=trackshow&id={$tmx->id}]{$name}\$l";
        }

        return $name;
    }

    private function formatTime(int $seconds): string
    {
        return Aseco::formatTimeH($seconds * 1000, false);
    }
}
