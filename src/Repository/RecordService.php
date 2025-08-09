<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Repository\{ChallengeService, RepositoryManager};

class RecordService
{
    private string $uid = '';

    public function __construct(
        protected RepositoryManager $repository,
        protected ChallengeService $challengeService
    ) {
        $this->uid = $this->challengeService->getUid();
        // creates empty record if uid not exist for update
        $this->createRecord($this->uid, Container::fromArray(['ChallengeId' => $this->uid]));
    }

    public function updateLeaderboard(
        string $challengeId,
        string $playerId,
        int $newTime
    ): void {
        if (!$this->exists($challengeId)) {
            return;
        }

        $times = $this->repository->get(Table::RECORDS, 'ChallengeId', $challengeId);

        // Filter out existing player's entry if it exists
        $times = array_filter($times, fn ($entry) => $entry['playerId'] !== $playerId);

        // Determine if the new time qualifies for top 30
        if (count($times) >= 30) {
            $maxTime = max(array_column($times, 'time'));

            // If new time is worse than the worst in top 30
            if ($newTime >= $maxTime) {
                return;
            }
        }

        // Add the new or improved time
        $times[] = ['playerId' => $playerId, 'time' => $newTime];

        // keep only top 30
        usort($times, fn ($a, $b) => $a['time'] <=> $b['time']);
        $times = array_slice($times, 0, 30);

        $record = Container::fromArray([
            'ChallengeId' => $challengeId,
            'Times' => $times
        ]);

        $this->repository->update(Table::RECORDS, $record);
    }

    public function getRecord(?string $challengeId = null): Container
    {
        $uid = $challengeId ?? $this->uid;

        if (!$this->exists($uid)) {
            return Container::fromArray([
                'Times' => []
            ], true);
        }

        // This is 100% json string it can be empty {}
        $records = $this->repository->get(Table::RECORDS, 'ChallengeId', $challengeId, ['Times']);

        return Container::fromJsonString($records);
    }

    public function createRecord(string $challengeId, Container $records): void
    {
        if (!$this->exists($challengeId)) {
            $this->repository->insert(Table::RECORDS, $records);
        }
    }

    private function exists(string $challengeId): bool
    {
        return $this->repository->exists(Table::RECORDS, 'ChallengeId', $challengeId);
    }
}
