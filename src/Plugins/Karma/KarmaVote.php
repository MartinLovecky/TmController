<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\{Aseco, HttpClient};
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class KarmaVote
{
    private int $retryTime = 0;
    private const RETRY_WAIT = 300; // 5 minutes

    public function __construct(
        private ChallengeService $challengeService,
        private HttpClient $httpClient,
        private PlayerService $playerService,
        private KarmaState $state
    ) {
    }

    // =================== Handle Chat Commands ===================
    public function handleChatCommand(TmContainer $chat): void
    {
        $command = strtolower($chat->get('command.name'));
        $valid = ['plus','plusplus','plusplusplus','minus','minusminus','minusminusminus'];

        if (!in_array($command, $valid, true)) {
            return;
        }

        $player = $this->playerService->getPlayerByLogin($chat->get('Login'));
        $vote = $this->mapCommandToVote($command);

        if ($vote !== null) {
            $this->recordVote($player, $vote);
        }
    }

    public function handleVoteResponse(TmContainer $answer): void
    {
        $login = $answer->get('Login') ?? $answer->get('login') ?? null;
        if (!$login) {
            return;
        }

        $action = $this->extractActionFromAnswer($answer);
        if ($action === null) {
            return; // nothing to do
        }

        $vote = $this->mapActionToVote($action);
        if ($vote === null) {
            return; // not a karma-related action
        }

        $player = $this->playerService->getPlayerByLogin($login);
        if ($player instanceof TmContainer) {
            $this->recordVote($player, $vote);
        }
    }

    private function extractActionFromAnswer(TmContainer $a): int|string|null
    {
        $keys = [
        'Action','action','ActionID','actionid','action_id',
        'manialink.action','id','value'
        ];

        foreach ($keys as $k) {
            $v = $a->get($k);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        foreach (['1','2'] as $k) {
            $v = $a->get($k);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        return null;
    }

    private function mapCommandToVote(string $command): ?int
    {
        $numericMapping = KarmaState::VOTE_MAPPING;
        $symbolMapping = [
            '+++' => 3,
            '++'  => 2,
            '+'   => 1,
            '-'   => -1,
            '--'  => -2,
            '---' => -3,
        ];

        return $numericMapping[$command] ?? $symbolMapping[$command] ?? null;
    }

    private function mapActionToVote(int|string $action): ?int
    {
        if (is_numeric($action)) {
            $id = (int) $action;
            return KarmaState::VOTE_MAPPING[$id] ?? null;
        }

        $s = strtolower((string) $action);

        $symbolMapping = [
        '+++' => 3, 'plusplusplus' => 3,
        '++'  => 2, 'plusplus'     => 2,
        '+'   => 1, 'plus'         => 1,
        '-'   => -1, 'minus'       => -1,
        '--'  => -2, 'minusminus'  => -2,
        '---' => -3, 'minusminusminus' => -3,
        ];

        return $symbolMapping[$s] ?? null;
    }

    // =================== Record Vote ===================
    public function recordVote(TmContainer $player, int $vote): void
    {
        $login = $player->get('Login');
        $previousVote = $this->state->getPlayerVote($login) ?? 0;

        $this->state->updateVoteCount('global', $previousVote, $vote);
        $this->state->updatePlayerVote($login, $vote, $previousVote);
        $this->state->calculateKarma(['global']);
    }

    // =================== Store Votes to API with Retry ===================
    public function storeVotesToAPI(): void
    {
        if (!$this->shouldRetry()) {
            return;
        }

        $karmaState = $this->state->getKarmaState();
        $players = $karmaState['new']['players'] ?? [];

        if (empty($players)) {
            return;
        }

        $votes = [];
        foreach ($players as $login => $v) {
            $val = is_array($v) ? (int)($v['vote'] ?? 0) : (int)$v;
            $votes[] = urlencode($login) . '=' . $val;
        }

        $gbx = $this->challengeService->getGBX();
        $response = $this->httpClient->get($_ENV['KarmaAPI'], [
            'Action'   => 'Vote',
            'login'    => $_ENV['server_login'],
            'authcode' => $_ENV['authcode'],
            'uid'      => $karmaState['data']['uid'],
            'map'      => base64_encode($karmaState['data']['name']),
            'author'   => urlencode($karmaState['data']['author']),
            'atime'    => $gbx->authorTime,
            'ascore'   => $gbx->authorScore,
            'nblaps'   => $gbx->nbLaps,
            'nbchecks' => $gbx->nbChecks,
            'mood'     => $gbx->mood,
            'env'      => $karmaState['data']['env'],
            'votes'    => implode('|', $votes),
            'tmx'      => $karmaState['data']['id'],
        ]);

        if (!$response) {
            Aseco::consoleText("Failed to send votes. Will retry in " . self::RETRY_WAIT . " seconds.");
            $this->retryTime = time() + self::RETRY_WAIT;
            return;
        }

        $output = \Yuhzel\TmController\Infrastructure\Xml\Parser::fromXMLString($response);
        if ($output->get('status') !== 200) {
            Aseco::console("Storing votes failed with code {1}", $output->get('status'));
            $this->retryTime = time() + self::RETRY_WAIT;
        } else {
            $this->retryTime = 0;
            $this->state->clearNewVotes();
        }
    }

    private function shouldRetry(): bool
    {
        return $this->retryTime === 0 || time() >= $this->retryTime;
    }
}
