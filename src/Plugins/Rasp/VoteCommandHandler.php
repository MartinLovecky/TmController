<?php

namespace Yuhzel\TmController\Plugins\Rasp;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\{ManiaLinks, Track};
use Yuhzel\TmController\Plugins\Rasp\{VoteType, RaspState};
use Yuhzel\TmController\App\Service\{Aseco, Log};
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\{ChallengeService, PlayerService};

class VoteCommandHandler
{
    protected ?ManiaLinks $maniaLinks = null;
    protected ?Track $track = null;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected PlayerService $playerService,
        protected RaspState $raspState,
        protected VoteManager $voteManager,
    ) {
    }

    public function setDependecy(ManiaLinks $maniaLinks, Track $track): void
    {
        $this->maniaLinks = $maniaLinks;
        $this->track = $track;
    }

    public function handleSkip(TmContainer $player): bool
    {
        return $this->tryStartVote(
            $player,
            'skip',
            $this->raspState->max_skipvotes,
            fn($type, $login) => $this->checkModeLimits($type, $login)
        );
    }

    public function handleEndRound(TmContainer $player): bool
    {
        $mode = $this->challengeService->getGameMode();
        if (in_array($mode, ['time_attack', 'laps', 'stunts'], true)) {
            return $this->sendError($player->get('Login'), 'Mode does not support /endround vote!');
        }

        return $this->tryStartVote(
            $player,
            'endround',
            $this->raspState->max_laddervotes
        );
    }

    public function handleLadder(TmContainer $player): bool
    {
        return $this->tryStartVote(
            $player,
            'ladder',
            $this->raspState->max_laddervotes,
            fn($type, $login) => $this->checkModeLimits($type, $login)
        );
    }

    public function handleReplay(TmContainer $player): bool
    {
        $login = $player->get('Login');

        if (
            $this->raspState->replays_limit > 0
            && $this->raspState->replays_counter >= $this->raspState->replays_limit
        ) {
            return $this->sendError($login, "No more replays allowed ({$this->raspState->replays_limit})");
        }

        $currentUid = $this->challengeService->getUid();
        if (isset($this->raspState->jukebox[$currentUid])) {
            return $this->sendError($login, 'Track is already being replayed!');
        }

        return $this->tryStartVote(
            $player,
            'replay',
            $this->raspState->max_replayvotes,
            fn($type, $login) => $this->checkModeLimits($type, $login, true)
        );
    }

    public function handleKick(TmContainer $player): bool
    {
        $login = $player->get('Login');
        $targetLogin = $player->get('command.params');

        if (!$targetLogin) {
            return $this->sendError($login, '/kick needs player login');
        }

        $targetPlayer = $this->playerService->getPlayerByLogin($targetLogin);
        if (!$targetPlayer) {
            return $this->sendError($login, 'Player not found (case sensitive)');
        }

        if (!$this->raspState->allow_kickvotes) {
            return $this->sendError($login, 'Kick votes not allowed!');
        }

        return $this->tryStartVote($player, 'kick');
    }

    public function handleHelp(TmContainer $player): bool
    {
        $login = $player->get('Login');
        if (!$this->raspState->feature_votes) {
            return $this->sendError($login, Aseco::getChatMessage('no_vote', 'rasp'));
        }

        $header = '{#vote}Chat-based votes$g are available for these actions:';
        $data = [];
        $data[] = ['Ratio', '{#black}Command', ''];
        $voteTypes = [
            VoteType::ENDROUND,
            VoteType::LADDER,
            VoteType::REPLAY,
            VoteType::SKIP
        ];

        if ($this->raspState->allow_ignorevotes) {
            $voteTypes[] = VoteType::IGNORE;
        }
        if ($this->raspState->allow_kickvotes) {
            $voteTypes[] = VoteType::KICK;
        }

        foreach ($voteTypes as $voteType) {
            $ratio = $voteType->defaultRatio() * 100 . '%';
            $commandName = strtolower($voteType->label());
            $desc = $voteType->description();
            $data[] = [$ratio, "{#black}/$commandName", $desc];
        }

        $this->maniaLinks->displayManialink(
            $login,
            $header,
            ['Icons64x64_1', 'TrackInfo', -0.01],
            $data,
            [1.0, 0.1, 0.2, 0.7],
            'OK'
        );
        return true;
    }

    private function tryStartVote(
        TmContainer $player,
        string $type,
        ?int $maxVotes = null,
        ?callable $modeCheck = null
    ): bool {
        $login = $player->get('Login');
        if ($this->handleVoteCancel($player)) {
            return false;
        }
        $error = $this->canStartVote($player, $type, $maxVotes);
        if ($error) {
            return $this->sendError($login, $error);
        }
        if ($modeCheck) {
            $modeError = $modeCheck($type, $login);
            if ($modeError) {
                return false;
            }
        }
        $this->incrementVoteCounter($type);
        $voteTypeEnum = VoteType::from($type);
        $this->voteManager->startVote(
            $player,
            $voteTypeEnum->value,
            $voteTypeEnum->description(),
            $voteTypeEnum->defaultRatio()
        );
        return true;
    }

    private function incrementVoteCounter(string $type): void
    {
        $counterKey = "num_{$type}votes";
        if (property_exists($this->raspState, $counterKey)) {
            $this->raspState->$counterKey++;
        }
    }

    /**
     * Undocumented function
     *
     * @param TmContainer $player
     * @return bool
     */
    public function handleVoteCancel(TmContainer $player): bool
    {
        $commandName = strtolower($player->get('command.name'));
        $params      = strtolower($player->get('command.params'));
        $login       = $player->get('Login');

        if (!$this->raspState->chatvote) {
            return $this->sendError($login, 'There is no vote in progress!');
        }

        if ($login !== $this->raspState->chatvote->login) {
            return $this->sendError($login, 'You are not allowed to cancel this vote!');
        }

        if (!$this->isCancelCommand($commandName, $params)) {
            return false;
        }

        $this->voteManager->resetVotes();
        return true;
    }

    private function checkModeLimits(string $voteType, string $login, bool $isMinCheck = false): bool
    {
        $mode = $this->challengeService->getGameMode();
        if ($mode === 'rounds' && $this->raspState->r_points_limits) {
            return $this->checkRoundsPointsLimit($voteType, $login, $isMinCheck);
        } elseif ($mode === 'time_attack' && $this->raspState->ta_time_limits) {
            return $this->checkTimeAttackLimit($voteType, $login, $isMinCheck);
        }
        return false;
    }

    private function canStartVote(TmContainer $player, string $type, ?int $maxVotes = null): ?string
    {
        if (!$this->raspState->feature_votes) {
            return Aseco::getChatMessage('no_vote', 'rasp');
        }
        if ($this->raspState->disabled_scoreboard) {
            return Aseco::getChatMessage('no_sb_vote', 'rasp');
        }
        if ($this->raspState->allow_spec_startvote && $player->get('isSpectator')) {
            return Aseco::getChatMessage('no_spectators', 'rasp');
        }
        if (!empty($this->raspState->chatvote) || !empty($this->raspState->tmxadd)) {
            return Aseco::getChatMessage('vote_already', 'rasp');
        }

        if ($maxVotes !== null) {
            $numVotesKey = "num_{$type}votes";
            if ($this->raspState->$numVotesKey >= $maxVotes) {
                return Aseco::formatText(
                    Aseco::getChatMessage('vote_limit', 'rasp'),
                    $maxVotes,
                    "/$type",
                    $maxVotes == 1 ? '' : 's'
                );
            }
        }

        return null;
    }

    private function sendError(string $login, string $message): bool
    {
        $this->client->query('ChatSendServerMessageToLogin', [
            Aseco::formatColors("{#server}> {#error}{$message}"),
            $login
        ]);
        return false;
    }

    private function isCancelCommand(string $commandName, string $params): bool
    {
        $aliases = ['cancel', 'stop', 'abort'];
        return in_array($commandName, $aliases, true) || in_array($params, $aliases, true);
    }

    private function checkRoundsPointsLimit(string $voteType, string $login, bool $isMinCheck = false): bool
    {
        //NOTE: No clue what GetCurrentRanking query result is, so be safe
        $info = $this->client->query('GetCurrentRanking', [1, 0]);
        $points = $info->has('score') ? $info->get('score') : 0;
        /** @var int $limit */
        $limit = $this->client->query('GetRoundPointsLimit')->get('CurrentValue');
        $thresholdProperty = "r_{$voteType}_" . ($isMinCheck ? 'min' : 'max');
        $threshold = $this->raspState->$thresholdProperty ?? 0;
        $shouldBlock = $isMinCheck ? ($points < ($limit * $threshold)) : ($points > ($limit * $threshold));

        if ($shouldBlock) {
            $messageType = $isMinCheck ? 'too early' : 'too late';
            $msg = "First player " .
               ($isMinCheck ? "has only" : "already has") .
               " {#highlite}\$i{$points}{#error} points - {$messageType} for {$voteType}!";
            return $this->sendError($login, $msg);
        }

        return false;
    }

    private function checkTimeAttackLimit(string $voteType, string $login, bool $isMinCheck = false): bool
    {
        $played = $this->track->timePlaying();
        /** @var int $limit */
        $limit = $this->client->query('GetRoundPointsLimit')->get('CurrentValue');

        if ($limit > 0) {
            $limitSeconds = $limit / 1000;

            $thresholdProperty = "ta_{$voteType}_" . ($isMinCheck ? 'min' : 'max');
            $threshold = $this->raspState->$thresholdProperty ?? 0;

            $shouldBlock = $isMinCheck ?
               ($played < ($limitSeconds * $threshold)) :
               ($played > ($limitSeconds * $threshold));

            if ($shouldBlock) {
                $timeFormatted = preg_replace('/^00:/', '', Aseco::formatTimeH($played * 1000, false));
                $messageType = $isMinCheck ? 'too early' : 'too late';
                $action = $voteType === 'ladder' ? 'ladder restart' : $voteType;
                $msg = "Track is " .
                   ($isMinCheck ? "only playing for" : "already playing for") .
                   " {#highlite}\$i{$timeFormatted}{#error} minutes - {$messageType} for {$action}!";
                return $this->sendError($login, $msg);
            }
        }

        return false;
    }
}
