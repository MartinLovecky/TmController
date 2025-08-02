<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services\Karma;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\HttpClient;
use Yuhzel\TmController\Infrastructure\Xml\Parser;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Infrastructure\Gbx\ChallMapFetcher;
use Yuhzel\TmController\Repository\PlayerService;

class VoteService
{
    private int $retryTime = 0;
    private const RETRY_WAIT = 300;
    private const VOTE_MAPPING = [
        18 => 3,  // +++
        11 => 2,  // ++
        10 => 1,  // +
        14 => -1, // -
        15 => -2, // --
        16 => -3  // ---
    ];

    public function __construct(
        protected ChallengeService $challengeService,
        protected HttpClient $httpClient,
        protected PlayerService $playerService,
        protected StateService $stateService,
    ) {
    }

    public function storeKarmaVotes(): void
    {
        if (!$this->shouldStoreVotes()) {
            return;
        }

        $this->sendVotesToCentralDatabase();
    }

    private function shouldStoreVotes(): bool
    {
        return $this->retryTime === 0 &&
               !empty($this->stateService->getKarmaState()['new']['players']) &&
               $this->retryTime <= time();
    }

    private function sendVotesToCentralDatabase(): void
    {
        $gbx = $this->challengeService->getGBX();
        $karmaState = $this->stateService->getKarmaState();
        $votes = $this->formatVotesForApi();

        $response = $this->httpClient->get($_ENV['KarmaAPI'], [
            'Action' => 'Vote',
            'login' => $_ENV['server_login'],
            'authcode' => $_ENV['KarmaHash'],
            'uid' => $karmaState['data']['uid'],
            'map' => base64_encode($karmaState['data']['name']),
            'author' => urlencode($karmaState['data']['author']),
            'atime' => $this->getAuthorTime($gbx),
            'ascore' => $this->getAuthorScore($gbx),
            'nblaps' => $gbx->nbLaps,
            'nbchecks' => $gbx->nbChecks,
            'mood' => $gbx->mood,
            'env' => $karmaState['data']['env'],
            'votes' => implode('|', $votes),
            'tmx' => $karmaState['data']['id']
        ]);

        $this->handleVoteStorageResponse($response);
    }

    private function getAuthorTime(ChallMapFetcher $gbx): int
    {
        return ($this->stateService->getGameMode() === 'stunts') ? 0 : $gbx->authorTime;
    }

    private function getAuthorScore(ChallMapFetcher $gbx): int
    {
        return ($this->stateService->getGameMode() === 'stunts') ? $gbx->authorScore : 0;
    }

    private function formatVotesForApi(): array
    {
        $pairs = [];
        $newVotes = $this->stateService->getKarmaState()['new']['players'] ?? [];

        foreach ($newVotes as $login => $voteData) {
            $pairs[] = urlencode($login) . '=' . $voteData['vote'];
        }

        return $pairs;
    }

    private function handleVoteStorageResponse(string $response): void
    {
        $output = Parser::fromXMLString($response);

        if ($output->get('status') !== 200) {
            $this->handleVoteStorageFailure($output);
        } else {
            $this->handleVoteStorageSuccess();
        }
    }

    private function handleVoteStorageFailure(Container $output): void
    {
        Aseco::console("Storing votes failed with returncode {1}", $output->get('status'));
        $this->retryTime = time() + self::RETRY_WAIT;
    }

    private function handleVoteStorageSuccess(): void
    {
        $this->retryTime = time() + $this->retryTime;
        $this->stateService->clearNewVotes(); // Clear after successful storage
    }

    public function handleVoteResponse(array $answer): void
    {
        $player = $this->playerService->getPlayerByLogin($answer['login']);
        $action = (int) $answer['answer'];
        $vote = self::VOTE_MAPPING[$action] ?? null;

        if ($vote !== null) {
            $this->recordVote($player, $vote);
        }
    }

    public function recordVote(Container $player, int $vote): void
    {
        $login = $player->get('Login');
        $previousVote = $this->stateService->getPlayerVote($login) ?? 0;

        $this->stateService->updateVoteCount('global', $previousVote, $vote);
        $this->stateService->updatePlayerVote($login, $vote, $previousVote);

        $this->stateService->calculateKarma(['global']);
    }

    public function getRetryTime(): int
    {
        return $this->retryTime;
    }

    public function shouldRetry(): bool
    {
        return $this->retryTime > 0 && time() >= $this->retryTime;
    }
}
