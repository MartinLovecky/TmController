<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\{Aseco, HttpClient};
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class KarmaManager
{
    private int $retryTime = 0;
    private const RETRY_WAIT = 300;

    public function __construct(
        private ChallengeService $challengeService,
        private HttpClient $httpClient,
        private PlayerService $playerService,
        private KarmaState $state
    ) {
    }

    public function exportVotes(): void
    {
        if (!$this->shouldStoreVotes()) {
            return;
        }
        $this->sendVotesToCentralDatabase();
    }

    private function shouldStoreVotes(): bool
    {
        $karmaState = $this->state->getKarmaState();
        return $this->retryTime === 0 && !empty($karmaState['new']['players']);
    }

    private function sendVotesToCentralDatabase(): void
    {
        $karmaState = $this->state->getKarmaState();
        $votes = [];

        foreach ($karmaState['new']['players'] ?? [] as $login => $voteData) {
            $votes[] = urlencode($login) . '=' . $voteData['vote'];
        }

        $gbx = $this->challengeService->getGBX();

        $response = $this->httpClient->get($_ENV['KarmaAPI'], [
            'Action' => 'Vote',
            'login' => $_ENV['server_login'],
            'authcode' => $_ENV['authcode'],
            'uid' => $karmaState['data']['uid'],
            'map' => base64_encode($karmaState['data']['name']),
            'author' => urlencode($karmaState['data']['author']),
            'atime' => $gbx->authorTime,
            'ascore' => $gbx->authorScore,
            'nblaps' => $gbx->nbLaps,
            'nbchecks' => $gbx->nbChecks,
            'mood' => $gbx->mood,
            'env' => $karmaState['data']['env'],
            'votes' => implode('|', $votes),
            'tmx' => $karmaState['data']['id']
        ]);

        if (!$response) {
            Aseco::consoleText("Failed to send votes. Check HttpClient logs.");
            return;
        }

        $this->handleVoteStorageResponse($response);
    }

    private function handleVoteStorageResponse(string $response): void
    {
        $output = \Yuhzel\TmController\Infrastructure\Xml\Parser::fromXMLString($response);
        if ($output->get('status') !== 200) {
            $this->retryTime = time() + self::RETRY_WAIT;
        } else {
            $this->retryTime = 0;
            $this->state->clearNewVotes();
        }
    }

    public function handleVote(TmContainer $player, int $vote): void
    {
        $login = $player->get('Login');
        $prev = $this->state->getPlayerVote($login) ?? 0;

        $this->state->updateVoteCount('global', $prev, $vote);
        $this->state->updatePlayerVote($login, $vote, $prev);
        $this->state->calculateKarma(['global']);
    }

    public function shouldRetry(): bool
    {
        return $this->retryTime > 0 && time() >= $this->retryTime;
    }
}
