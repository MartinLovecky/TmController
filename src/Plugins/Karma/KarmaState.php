<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Repository\{ChallengeService, PlayerService, RecordService};

class KarmaState
{
    public array $karma = [];
    public const VOTE_TYPES = ['fantastic','beautiful','good','bad','poor','waste'];
    public const VOTE_MAPPING = [18 => 3,11 => 2,10 => 1,14 => -1,15 => -2,16 => -3];

    public function __construct(
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private RecordService $recordService
    ) {
    }

    public function getKarmaState(): array
    {
        return $this->karma;
    }
    public function setKarmaState(array $karma): void
    {
        $this->karma = $karma;
    }

    public function updateVoteCount(string $location, int $old, int $new): void
    {
        $votes = &$this->karma[$location]['votes'];
        $this->modifyVoteCount($votes, $old, -1);
        $this->modifyVoteCount($votes, $new, +1);
    }

    private function modifyVoteCount(array &$votes, int $vote, int $delta): void
    {
        $type = $this->voteValueToType($vote);
        if ($type) {
            $votes[$type]['count'] = max(0, ($votes[$type]['count'] ?? 0) + $delta);
        }
    }

    private function voteValueToType(int $vote): ?string
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

    public function updatePlayerVote(string $login, int $vote, int $previous): void
    {
        $this->karma['new']['players'][$login] = $vote;
        $this->karma['global']['players'][$login] = ['vote' => $vote,'previous' => $previous];
    }

    public function getPlayerVote(string $login): ?int
    {
        return $this->karma['global']['players'][$login]['vote'] ?? null;
    }

    public function clearNewVotes(): void
    {
        $this->karma['new']['players'] = [];
    }

    public function calculateKarma(array $locations): void
    {
        foreach ($locations as $loc) {
            $votes = &$this->karma[$loc]['votes'];
            $total = max(1, array_sum(array_map(fn($v)=>$v['count'], $votes)));
            $votes['karma'] = (int) floor(
                ( ($votes['fantastic']['count'] * 3) +
                  ($votes['beautiful']['count'] * 2) +
                  ($votes['good']['count']) +
                  ($votes['bad']['count'] * -1) +
                  ($votes['poor']['count'] * -2) +
                  ($votes['waste']['count'] * -3)
                )
            );
            foreach (self::VOTE_TYPES as $t) {
                $votes[$t]['percent'] = sprintf('%.2f', ($votes[$t]['count'] / $total) * 100);
            }
            $votes['total'] = $total;
        }
    }
}
