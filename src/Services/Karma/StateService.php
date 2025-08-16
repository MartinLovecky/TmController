<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services\Karma;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Repository\RecordService;

class StateService
{
    public array $karma = [];
    private const VOTE_TYPES = [
        'fantastic', 'beautiful', 'good', 'bad', 'poor', 'waste'
    ];

    public function __construct(
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected RecordService $recordService,
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

    public function initializeKarmaState(): void
    {
        if (Aseco::$startupPhase) {
            $this->initializeForNewMap();
        }

        $this->calculateKarma(['global', 'local']);
    }

    public function initializeForNewMap(): void
    {
        $tmx = $this->challengeService->getTMX();
        $gbx = $this->challengeService->getGBX();

        $this->karma = $this->createNewKarmaState();
        $this->karma['data'] = [
            'uid'    => $tmx->uid ?? $gbx->UId,
            'id'     => $tmx->id ?? 0,
            'name'   => $tmx->name ?? Aseco::stripColors($gbx->name),
            'author' => $tmx->author ?? Aseco::stripColors($gbx->author),
            'env'    => $tmx->envir ?? $gbx->envir,
        ];
        $this->karma['new']['players'] = $this->karma['local']['players'];
    }

    public function createNewKarmaState(): array
    {
        $state = [
            'data' => [],
            'global' => [
                'votes' => $this->getEmptyVotes(),
                'players' => [],
            ],
            'local' => $this->getLocalKarma($this->challengeService->getUid()),
        ];

        $logins = $this->playerService->getAllLogins();
        $playerVoteData = ['vote' => 0, 'previous' => 0];

        $state['global']['players'] = array_fill_keys($logins, $playerVoteData);

        if (Aseco::isStartup()) {
            $state['local']['players'] = array_fill_keys($logins, $playerVoteData);
        }

        return $state;
    }

    public function getEmptyVotes(): array
    {
        $votes = ['karma' => 0, 'total' => 0];

        foreach (self::VOTE_TYPES as $type) {
            $votes[$type] = ['percent' => 0, 'count' => 0];
        }

        return $votes;
    }

    public function getLocalKarma(?string $mapId = null): array
    {
        if (!$mapId) {
            return $this->getEmptyLocalKarma();
        }

        $this->initializeVoteCounts('local');
        $this->fetchKarmaFromDatabase($mapId);
        $this->calculateKarma(['local']);

        return $this->karma['local'] ?? $this->getEmptyLocalKarma();
    }

    private function getEmptyLocalKarma(): array
    {
        return [
            'votes' => $this->getEmptyVotes(),
            'players' => [],
        ];
    }

    private function fetchKarmaFromDatabase(string $mapId): void
    {
        // Placeholder for future DB implementation
        // $result = $this->fluent->query
        //     ->from('rs_karma')
        //     ->where('ChallengeId', $mapId)
        //     ->fetch();

        // if ($result) {
        //     // Process database results
        // }
    }

    private function initializeVoteCounts(string $location): void
    {
        foreach (self::VOTE_TYPES as $type) {
            $this->karma[$location]['votes'][$type] = ['percent' => 0, 'count' => 0];
        }
    }

    public function calculateKarma(array $locations, $def = 'default'): void
    {
        foreach ($locations as $location) {
            $votes = &$this->karma[$location]['votes'];
            $this->normalizeVoteCounts($votes);
            $totalVotes = $this->calculateTotalVotes($votes);

            $this->calculatePercentages($votes, $totalVotes);
            $this->calculateWeightedKarma($votes, $totalVotes, $def);
            $votes['total'] = (int) $totalVotes;
        }
    }

    private function normalizeVoteCounts(array &$votes): void
    {
        foreach (self::VOTE_TYPES as $type) {
            if ($votes[$type]['count'] < 0) {
                $votes[$type]['count'] = 0;
            }
        }
    }

    private function calculateTotalVotes(array $votes): float
    {
        $total = 0;
        foreach (self::VOTE_TYPES as $type) {
            $total += $votes[$type]['count'];
        }
        return $total > 0 ? $total : 0.0000000000001;
    }

    private function calculatePercentages(array &$votes, float $totalVotes): void
    {
        foreach (self::VOTE_TYPES as $type) {
            $votes[$type]['percent'] = sprintf('%.2f', $votes[$type]['count'] / $totalVotes * 100);
        }
    }

    private function calculateWeightedKarma(
        array &$votes,
        float $totalVotes,
        string $def,
    ): void {
        if ($def === 'rasp') {
            $votes['karma'] = floor(
                ($votes['fantastic']['count'] * 3) +
                ($votes['beautiful']['count'] * 2) +
                ($votes['good']['count'] * 1) +
                -($votes['bad']['count'] * 1) +
                -($votes['poor']['count'] * 2) +
                -($votes['waste']['count'] * 3)
            );
        } else {
            $good = ($votes['fantastic']['count'] * 100) +
                    ($votes['beautiful']['count'] * 80) +
                    ($votes['good']['count'] * 60);
            $bad = ($votes['bad']['count'] * 40) +
                   ($votes['poor']['count'] * 20);

            $votes['karma'] = floor(($good + $bad) / $totalVotes);
        }
    }

    public function initializePlayerState(Container $player): void
    {
        if ($player->get('OnlineRights') === 3) {
            $player->set('ManiaKarma.LotteryPayout', 0);
        }

        $player->set('ManiaKarma.ReminderWindow', false);
        $player->set('ManiaKarma.FinishedMapCount', 0);

        $this->initializePlayerKarma($player);
    }

    private function initializePlayerKarma(Container $player): void
    {
        $uid = $this->karma['data']['uid'] ?? null;
        if (!$uid) {
            return;
        }
        $count = 0;
        $result = $this->recordService->getRecordForPlayer($uid, $player->get('Login'));

        if (!empty($result)) {
            // TODO: Initialize player's karma state from records
            dd($result);
        }
    }

    public function resetForNewChallenge(): void
    {
        $this->playerService->eachPlayer(function (Container $player) {
            $player->set('ManiaKarma.FinishedMapCount', 0);
        });
    }

    public function updateVoteCount(string $location, int $oldVote, int $newVote): void
    {
        $votes = &$this->karma[$location]['votes'];
        $this->removeOldVote($votes, $oldVote);
        $this->addNewVote($votes, $newVote);
    }

    private function removeOldVote(array &$votes, int $oldVote): void
    {
        $oldType = $this->voteValueToType($oldVote);
        if ($oldType && isset($votes[$oldType])) {
            $votes[$oldType]['count'] = max(0, $votes[$oldType]['count'] - 1);
        }
    }

    private function addNewVote(array &$votes, int $newVote): void
    {
        $newType = $this->voteValueToType($newVote);
        if ($newType && isset($votes[$newType])) {
            $votes[$newType]['count']++;
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

    public function updatePlayerVote(string $login, int $vote, int $previousVote): void
    {
        $this->karma['new']['players'][$login] = $vote;
        $this->karma['global']['players'][$login] = [
            'vote' => $vote,
            'previous' => $previousVote
        ];
    }

    public function clearNewVotes(): void
    {
        if (isset($this->karma['new']['players'])) {
            $this->karma['new']['players'] = [];
        }
    }

    public function getPlayerVote(string $login): ?int
    {
        return $this->karma['global']['players'][$login]['vote'] ?? null;
    }

    public function getPreviousVote(string $login): ?int
    {
        return $this->karma['global']['players'][$login]['previous'] ?? null;
    }
}
