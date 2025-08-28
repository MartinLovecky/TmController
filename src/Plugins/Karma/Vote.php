<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\{Aseco, HttpClient};
use Yuhzel\TmController\Plugins\Karma\State;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};
use Yuhzel\TmController\Infrastructure\Xml\Parser;
use Yuhzel\TmController\Infrastructure\Gbx\ChallMapFetcher;

class Vote
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
        protected State $state,
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
               !empty($this->state->getKarmaState()['new']['players']) &&
               $this->retryTime <= time();
    }

    private function sendVotesToCentralDatabase(): void
    {
        $gbx = $this->challengeService->getGBX();
        $karmaState = $this->state->getKarmaState();
        $votes = $this->formatVotesForApi();

        $response = $this->httpClient->get($_ENV['KarmaAPI'], [
            'Action' => 'Vote',
            'login' => $_ENV['server_login'],
            'authcode' => $_ENV['authcode'],
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

        if (!$response) {
            Aseco::consoleText("Failed to send votes to Karma API. Aseco\HttpClient logs for details.");
            return;
        }

        $this->handleVoteStorageResponse($response);
    }

    private function getAuthorTime(ChallMapFetcher $gbx): int
    {
        return $gbx->authorTime;
    }

    private function getAuthorScore(ChallMapFetcher $gbx): int
    {
        return $gbx->authorScore;
    }

    private function formatVotesForApi(): array
    {
        $pairs = [];
        $newVotes = $this->state->getKarmaState()['new']['players'] ?? [];

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

    private function handleVoteStorageFailure(TmContainer $output): void
    {
        Aseco::console("Storing votes failed with returncode {1}", $output->get('status'));
        $this->retryTime = time() + self::RETRY_WAIT;
    }

    private function handleVoteStorageSuccess(): void
    {
        $this->retryTime = time() + $this->retryTime;
        $this->state->clearNewVotes(); // Clear after successful storage
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

    public function recordVote(TmContainer $player, int $vote): void
    {
        $login = $player->get('Login');
        $previousVote = $this->state->getPlayerVote($login) ?? 0;

        $this->state->updateVoteCount('global', $previousVote, $vote);
        $this->state->updatePlayerVote($login, $vote, $previousVote);

        $this->state->calculateKarma(['global']);
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
