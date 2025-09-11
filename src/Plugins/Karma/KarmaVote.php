<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\App\Service\HttpClient;
use Yuhzel\TmController\App\Service\Log;
use Yuhzel\TmController\Plugins\Karma\KarmaVoteDTO;
use Yuhzel\TmController\Plugins\Karma\KarmaVoteType;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Repository\KarmaService;

class KarmaVote
{
    public ?KarmaVoteDTO $vote = null;
    public const array ALLOWED_COMMANDS = [
        '/karma', 'karma' , 'plus', 'plusplus', 'plusplusplus',
        'minus', 'minusminus', 'minusminusminus'
    ];
    private const int MAX_VOTES = 10;

    public function __construct(
        private ChallengeService $challengeService,
        private KarmaService $karmaService,
        private HttpClient $httpClient,
    ) {
    }

    public function startVote(string $type, string $login): void
    {
        $voteTypeEnum = KarmaVoteType::from($type);
        $this->vote ??= new KarmaVoteDTO([
            'login' => $login,
            'type'  => $voteTypeEnum->value,
            'votes' => [],
            'label' => $voteTypeEnum->label()
        ]);

        if (!$this->vote->addVote($voteTypeEnum)) {
            // duplicate vote, ignore
            return;
        }

        if ($this->vote->count == self::MAX_VOTES) {
            return;
        }

        if ($this->recordLocalVote()) {
            $this->storeVotesToAPI();
        }
    }

    public function recordLocalVote(): bool
    {
        if (!isset($this->vote) || empty($this->vote->votes)) {
            return false;
        }

        $challengeId = $this->challengeService->getUid();
        $currentVote = end($this->vote->votes);
        $previousDBType = $this->karmaService->getVote($this->vote->login, $challengeId);
        $previousDBVote = KarmaVoteType::from($previousDBType);

        if ($previousDBVote->value === $currentVote['type']) {
            return false;
        }

        // Persist vote to DB
        return $this->karmaService->castVote(
            $this->vote->login,
            $challengeId,
            $currentVote['type'],
            $currentVote['weight']
        );
    }

    public function storeVotesToAPI()
    {
        if (!isset($this->vote) || empty($this->vote->votes)) {
            return false;
        }

        $gbx = $this->challengeService->getGBX();
        $lastVote = end($this->vote->votes); // get last vote
        // Decide what API expects: weight, ID, or string
        $voteValue = $lastVote['weight'];
        $voteParam = urlencode($this->vote->login) . '=' . $voteValue;

        $response = $this->httpClient->get($_ENV['KarmaAPI'], [
            'Action'   => 'Vote',
            'login'    => $_ENV['server_login'],
            'authcode' => $_ENV['authcode'],
            'uid'      => $gbx->UId,
            'map'      => base64_encode($gbx->name),
            'author'   => urlencode($gbx->author),
            'atime'    => $gbx->authorTime,
            'ascore'   => $gbx->authorScore,
            'nblaps'   => $gbx->nbLaps,
            'nbchecks' => $gbx->nbChecks,
            'mood'     => $gbx->mood,
            'env'      => $gbx->envir,
            'votes'    => $voteParam,
            'tmx'      => $this->challengeService->getTMX()->id,
        ]);

        $output = \Yuhzel\TmController\Infrastructure\Xml\Parser::fromXMLString($response);

        Log::debug('storeVotesToAPI', $output->toArray(), 'KarmaVote');
        dd($output);
    }
}
