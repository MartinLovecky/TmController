<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Database\Table;

class KarmaService
{
    public function __construct(private RepositoryManager $repositoryManager)
    {
    }

    /**
     * Incert or Update vote
     *
     * @param string $playerId
     * @param string $challengeId
     * @param string $voteType
     * @param integer $score
     * @return boolean Returns true if the vote was changed.
     */
    public function castVote(
        string $playerId,
        string $challengeId,
        string $voteType,
        int $score = 0
    ): bool {
        $existingVote = $this->getVote($playerId, $challengeId);
        $conditions = ['playerID' => $playerId, 'ChallengeId' => $challengeId];

        if ($existingVote === null) {
            $this->repositoryManager->insert(Table::RS_KARMA, [
                'playerID'    => $playerId,
                'ChallengeId' => $challengeId,
                'Vote'        => $voteType,
                'Score'       => $score
            ], $conditions);
            return false; // no change, just a new vote
        }

        if ($existingVote === $voteType) {
            return false;
        }

        $this->repositoryManager->update(Table::RS_KARMA, [
            'Vote'  => $voteType,
            'Score' => $score
        ], $conditions);

        return true;
    }

    /**
     *  Get the existing vote for a player in a challenge.
     *
     * @param string $playerId
     * @param string $challengeId
     * @return string
     */
    public function getVote(string $playerId, string $challengeId): string
    {
        $conditions = ['playerID' => $playerId, 'ChallengeId' => $challengeId];

        // fetch single column 'Vote'
        $vote = $this->repositoryManager->fetch(Table::RS_KARMA, $conditions, null, ['Vote']);

        return $vote ?? 'none';
    }

    /**
     * Check if the current vote differs from a given vote type.
     *
     * @param string $playerId
     * @param string $challengeId
     * @param string $voteType
     * @return boolean
     */
    public function isVoteChanged(string $playerId, string $challengeId, string $voteType): bool
    {
        $existingVote = $this->getVote($playerId, $challengeId);

        if ($existingVote === null) {
            return false; // No existing vote
        }

        return $existingVote !== $voteType;
    }
}
