<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Repository\{RepositoryManager};

class RecordService
{
    private const DEFAULT_TOP_LIMIT = 30;

    public function __construct(private RepositoryManager $repository)
    {
    }

    /**
     * Update a leaderboard for a given challenge.
     * Keeps only top N times (default 30).
     *
     * @param string $challengeId
     * @param string $playerId
     * @param integer $newTime
     * @return void
     */
    public function updateLeaderboard(
        string $challengeId,
        string $playerId,
        int $newTime
    ): void {
        $record = $this->fetchSingle($challengeId);
        $times = $record->get('Times', []);

        // Remove any existing entry for this player
        $times = array_filter($times, fn ($entry) => $entry['playerId'] !== $playerId);

        // Only add if qualifies for top N
        if (count($times) >= self::DEFAULT_TOP_LIMIT) {
            $maxTime = max(array_column($times, 'time'));
            if ($newTime >= $maxTime) {
                return;
            }
        }

        $times[] = ['playerId' => $playerId, 'time' => $newTime];

        // Keep top N sorted
        usort($times, fn ($a, $b) => $a['time'] <=> $b['time']);
        $times = array_slice($times, 0, self::DEFAULT_TOP_LIMIT);

        $recordData = [
            'Times' => json_encode($times),
            'Checkpoints' => json_encode($record->get('Checkpoints', []))
        ];

        $this->repository->update(Table::RECORDS, $recordData, $challengeId);
    }

    /**
     * Fetch a record for a single challenge.
     *
     * @param string $challengeId
     * @return TmContainer
     */
    public function fetchSingle(string $challengeId): TmContainer
    {
        $record = $this->repository->fetch(Table::RECORDS, 'ChallengeId', $challengeId);

        if (!$record) {
            return TmContainer::fromArray([]);
        }

        $record['Times'] = json_decode($record['Times'] ?? '[]', true);
        $record['Checkpoints'] = json_decode($record['Checkpoints'] ?? '[]', true);

        return TmContainer::fromArray($record);
    }

    /**
     * Fetch leaderboard for multiple challenges (batch).
     *
     * @param string[] $challengeIds
     * @param int $limit
     * @return array<string, array> keyed by challengeId
     */
    public function getTopTimes(array $challengeIds, int $limit = self::DEFAULT_TOP_LIMIT): array
    {
        $all = [];
        foreach ($challengeIds as $challengeId) {
            $record = $this->fetchSingle($challengeId);
            $times = $record->get('Times', []);
            usort($times, fn ($a, $b) => $a['time'] <=> $b['time']);
            $all[$challengeId] = array_slice($times, 0, $limit);
        }
        return $all;
    }

    /**
     * Get a player's best time on a challenge.
     *
     * @param string $challengeId
     * @param string $playerId
     * @return array|null
     */
    public function getRecordForPlayer(string $challengeId, string $playerId): ?array
    {
        $record = $this->fetchSingle($challengeId);
        $times = $record->get('Times', []);

        foreach ($times as $entry) {
            if ($entry['playerId'] === $playerId) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Create an empty record for a challenge if none exists.
     *
     * @param string $challengeId
     * @return void
     */
    public function createEmptyRecord(string $challengeId): void
    {
        $existing = $this->repository->fetch(Table::RECORDS, 'ChallengeId', $challengeId);
        if ($existing) {
            return;
        }

        $data = [
            'ChallengeId' => $challengeId,
            'Times' => json_encode([]),
            'Checkpoints' => json_encode([])
        ];

        $this->repository->insert(Table::RECORDS, $data, $challengeId);
    }
}
