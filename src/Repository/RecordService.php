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
        //protected ChallengeService $challengeService
    ) {
        //$this->uid = //$this->challengeService->getUid();
        //$this->createRecord($this->uid, Container::fromArray(['ChallengeId' => $this->uid]));
    }

    public function updateLeaderboard(
        string $challengeId,
        string $playerId,
        int $newTime
    ): void {
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

        //TODO : Checkpoints
        $record = [
            'Times' => $times
        ];

        $this->repository->update(Table::RECORDS, $record, $challengeId);
    }

    public function getRecord(string $challengeId): Container
    {
        /** @var ?array $record */
        $record = $this->repository->get(Table::RECORDS, 'ChallengeId', $challengeId);

        if (!isset($record)) {
            return Container::fromArray([]);
        }

        $record['Times'] = json_decode($record['Times'], true);
        $record['Checkpoints'] = json_decode($record['Checkpoints'], true);

        return Container::fromArray($record);
    }

    public function getRecordForPlayer(string $challengeId, string $playerId): array
    {
        $record = $this->getRecord($challengeId);
        if ($record->has("Times.{$playerId}")) {
            return [$playerId => $record->get("Times.{$playerId}")];
        }
        return [];
    }

    public function createEmptyRecord(string $challengeId): void
    {
        $data = [
            'challengeId' => $challengeId,
            'Times' => '{}',
            'Checkpoints' => '{}'
        ];
        $this->repository->insert(Table::RECORDS, $data, $challengeId);
    }
}
