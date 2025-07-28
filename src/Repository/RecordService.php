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
        $this->uid = $this->challengeService->getGBX()->UId;
    }

    //NOTE: checkpoints not sure how get info from server
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

            // If new time is worse than the worst in top 30, skip
            if ($newTime >= $maxTime) {
                return;
            }
        }

        // Add the new or improved time
        $times[] = ['playerId' => $playerId, 'time' => $newTime];

        // Sort and keep only top 30
        usort($times, fn ($a, $b) => $a['time'] <=> $b['time']);
        $times = array_slice($times, 0, 30);

        $record = Container::fromArray([
            'ChallengeId' => $challengeId,
            'Times' => $times
        ]);

        if ($this->repository->exists(Table::RECORDS, 'ChallengeId', $challengeId)) {
            $this->repository->update(Table::RECORDS, $record);
        } else {
            $this->repository->insert(Table::RECORDS, $record);
        }
    }

    public function getRecord(?string $challengeId = null): Container
    {
        //REVIEW: this is bit hack-ish since -> getRecord for id thats not in db but we saying
        // not found not problem here is readonly Container without any Times could be used for "empty creation"
        // but I dont know if I want "pre-insert / create" empty record for sake just to use upate later
        // if we try "update" record that doesnt exist it will thow error we need have valid "challengeId" record in DB
        if (!$this->exists($challengeId)) {
            return Container::fromArray([
                'Times' => []
            ], true);
        }

        // This is 100% json string it can be empty {}
        $records = $this->repository->get(Table::RECORDS, 'ChallengeId', $challengeId, ['Times']);

        return Container::fromJsonString($records);
    }

    public function createRecord()
    {
        // we could 1st insert empty record for challengeId if no times driven to be able to update them
        if (!$this->exists()) {
            $record = $this->getRecord($this->uid);
        } else {
            //We want basicly do  updateLeaderboard but insert not update
            //$this->updateLeaderboard();
        }
    }

    private function exists(?string $challengeId = null): bool
    {
        return $this->repository->exists(Table::RECORDS, 'ChallengeId', $challengeId ?? $this->uid);
    }
}
