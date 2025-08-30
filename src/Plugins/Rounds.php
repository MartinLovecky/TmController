<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Aseco, Sender};
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};

class Rounds
{
    private int $roundsCount = 0;
    private array $roundTimes = [];
    private array $roundPbs = [];

    public function __construct(
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private RecordService $recordService,
        private Sender $sender,
    ) {
    }

    public function onSync(): void
    {
        $this->resetRounds();
    }

    public function onNewChallenge(): void
    {
        $this->resetRounds();
    }

    public function onRestartChallenge(): void
    {
        $this->resetRounds();
    }

    public function onEndRound(): void
    {
        if (!empty($this->roundTimes)) {
            $this->roundsCount++;
            $roundScores = [];
            ksort($this->roundTimes);
            foreach ($this->roundTimes as &$item) {
                if (count($item) > 1) {
                    $scores = [];
                    $pbs = [];
                    $pids = [];
                    foreach ($item as $key => &$row) {
                        $scores[$key] = $row['score'];
                        $pbs[$key] = $this->roundPbs[$row['login']];
                        $pids[$key] = $row['playerid'];
                    }
                    array_multisort($scores, SORT_NUMERIC, $pbs, SORT_NUMERIC, $pids, SORT_NUMERIC, $item);
                }
                $roundScores = array_merge($roundScores, $item);
            }
            $pos = 1;
            $new = false;
            $message = Aseco::formatText(Aseco::getChatMessage('round'), $this->roundsCount);

            foreach ($roundScores as $tm) {
                $player = $this->playerService->getPlayerByLogin($tm['login']);
                $login = $player->get('Login');
                $nick = Aseco::stripColors($player->get('NickName'));
                $curRecord = $this->recordService->getRecordForPlayer(
                    $this->challengeService->getUid(),
                    $login
                );
                if (in_array($login, $curRecord) && $curRecord[$login] <= $tm['score']) {
                    $new = true;
                }
                if ($new) {
                    $message .= Aseco::formatText(
                        Aseco::getChatMessage('ranking_record_new'),
                        $pos,
                        $nick,
                        Aseco::formatTime($tm['score'])
                    );
                //show_min_recs = 8
                } elseif ($pos <= 8) {
                    $message .= Aseco::formatText(
                        Aseco::getChatMessage('ranking_record'),
                        $pos,
                        $nick,
                        Aseco::formatTime($tm['score'])
                    );
                } else {
                    $message .= Aseco::formatText(
                        Aseco::getChatMessage('ranking_record2'),
                        $pos,
                        $nick,
                    );
                }
                $pos++;
            }

            $message = substr($message, 0, strlen($message) - 2);
            $this->sender->sendChatMessageToAll(message: $message, formatMode: Sender::FORMAT_COLORS);
            $this->roundTimes = [];
        }
    }

    public function onPlayerFinish(
        string $uid,
        string $login,
        string $date,
        int $score,
        bool $new
    ): void {
        if (
            $this->challengeService->getGameMode() === 'rounds'
            || $this->challengeService->getGameMode() === 'team'
            || $this->challengeService->getGameMode() === 'cup'
            && $score > 0
        ) {
            $this->roundTimes[$score] = [
                'playerid' => $login,
                'login' => $login,
                'score' => $score
            ];

            $this->roundPbs[$login] = $score;
        }
    }

    private function resetRounds(): void
    {
        reset($this->roundTimes);
        reset($this->roundPbs);
        $this->roundsCount = 0;
    }
}
