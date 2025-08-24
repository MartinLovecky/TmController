<?php

namespace Yuhzel\TmController\Plugins\Rasp;

use Yuhzel\TmController\Plugins\Track;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\Plugins\Rasp\VoteType;
use Yuhzel\TmController\Plugins\Rasp\RaspState;
use Yuhzel\TmController\App\Service\{Aseco, Log};
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;

class VoteCommandHandler
{
    public function __construct(
        private VoteManager $voteManager,
        private RaspState $raspState,
        private Client $client,
        private ChallengeService $challengeService,
        private PlayerService $playerService,
        private ManiaLinks $maniaLinks,
        private Track $track
    ) {
    }

    public function handleSkip(TmContainer $player): bool
    {
        $login = $player->get('Login');

        if ($this->handleVoteCancel($player)) {
            return false;
        }

        $error = $this->canStartVote($player, 'skip', $this->raspState->max_skipvotes);

        if ($error !== null) {
            return $this->sendError($login, $error);
        }

        $mode = $this->challengeService->getGameMode();

        if ($mode === 'rounds' && $this->raspState->r_points_limits) {
            return $this->checkRoundsPointsLimit('skip', $login);
        } elseif ($mode === 'time_attack' && $this->raspState->ta_time_limits) {
            return $this->checkTimeAttackLimit('skip', $login);
        }

        $this->raspState->num_skipvotes++;
        $this->voteManager->startVote(
            $player,
            VoteType::SKIP->value,
            VoteType::SKIP->description(),
            VoteType::SKIP->defaultRatio()
        );
        return true;
    }

    public function handleEndRound(TmContainer $player): bool
    {
        $login = $player->get('Login');

        if ($this->handleVoteCancel($player)) {
            return false;
        }

        $error = $this->canStartVote(
            $player,
            'endround',
            $this->raspState->max_laddervotes
        );

        if ($error !== null) {
            return $this->sendError($login, $error);
        }

        if (in_array($this->challengeService->getGameMode(), ['time_attack', 'laps', 'stunts'], true)) {
            return $this->sendError($login, 'Mode does not support /endround vote!');
        }

        $this->voteManager->startVote(
            $player,
            VoteType::ENDROUND->value,
            VoteType::ENDROUND->description(),
            VoteType::ENDROUND->defaultRatio()
        );

        return true;
    }

    public function handleLadder(TmContainer $player): bool
    {
        $login = $player->get('Login');

        if ($this->handleVoteCancel($player)) {
            return false;
        }

        $error = $this->canStartVote($player, 'ladder', $this->raspState->max_laddervotes);

        if ($error !== null) {
            return $this->sendError($login, $error);
        }

        $mode = $this->challengeService->getGameMode();
        if ($mode === 'rounds' && $this->raspState->r_points_limits) {
            return $this->checkRoundsPointsLimit('laddder', $login);
        } elseif ($mode === 'time_attack' && $this->raspState->ta_time_limits) {
            return $this->checkTimeAttackLimit('ladder', $login);
        }

        $this->raspState->num_laddervotes++;
        $this->voteManager->startVote(
            $player,
            VoteType::LADDER->value,
            VoteType::LADDER->description(),
            VoteType::LADDER->defaultRatio()
        );
        return true;
    }

    public function handleReplay(TmContainer $player): bool
    {
        $login = $player->get('Login');

        if ($this->handleVoteCancel($player)) {
            return false;
        }

        $error = $this->canStartVote($player, 'replay', $this->raspState->max_replayvotes);

        if ($error !== null) {
            return $this->sendError($login, $error);
        }

        if (
            $this->raspState->replays_limit > 0
            && $this->raspState->replays_counter >= $this->raspState->replays_limit
        ) {
            $msg = Aseco::formatText(
                Aseco::getChatMessage('no_more_replay', 'rasp'),
                $this->raspState->replays_limit,
                ($this->raspState->replays_limit == 1 ? '' : 's')
            );
            return $this->sendError($login, $msg);
        }

        // Check if track already in jukebox
        $currentUid = $this->challengeService->getUid();
        if (!empty($this->raspState->jukebox) && array_key_exists($currentUid, $this->raspState->jukebox)) {
            return $this->sendError($login, 'Track is already getting replayed!');
        }

        $mode = $this->challengeService->getGameMode();

        if ($mode === 'rounds' && $this->raspState->r_points_limits) {
            return $this->checkRoundsPointsLimit('replay', $login, true);
        } elseif ($mode === 'time_attack' && $this->raspState->ta_time_limits) {
            return $this->checkTimeAttackLimit('replay', $login, true);
        }

        $this->raspState->replays_counter++;
        $this->voteManager->startVote(
            $player,
            VoteType::REPLAY->value,
            VoteType::REPLAY->description(),
            VoteType::REPLAY->defaultRatio()
        );
        return true;
    }

    public function handleKick(TmContainer $player)
    {
        $login  = $player->get('Login');
        $params = $player->get('command.params');

        if (empty($params)) {
            return $this->sendError($login, "/kick needs player login");
        }

        $targetPlayer = $this->playerService->getPlayerByLogin($params);

        if (!$targetPlayer) {
            return $this->sendError($login, "Player not found its case sesitive !");
        }

        if ($this->handleVoteCancel($player)) {
            return false;
        }

        if (!$this->raspState->allow_kickvotes) {
            return $this->sendError($login, 'Kick votes not allowed!');
        }

        $error = $this->canStartVote($player, 'kick');

        if ($error !== null) {
            return $this->sendError($login, $error);
        }

        //TODO - NO KICK ADMIN :)

        $this->voteManager->startVote(
            $player,
            VoteType::KICK->value,
            'Kick ' . Aseco::stripColors($targetPlayer->get('NickName')),
            VoteType::KICK->defaultRatio()
        );
        $this->raspState->chatvote->target = $targetPlayer->get('Login');
        return true;
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

        if (empty($this->raspState->chatvote)) {
            return $this->sendError($login, 'There is no vote in progress!');
        }

        if ($login !== $this->raspState->chatvote->login) {
            return $this->sendError($login, 'You are not allowed to cancel this vote!');
        }

        if (!$this->isCancelCommand($commandName, $params)) {
            return false;
        }

        $this->voteManager->resetVotes($player);
        return true;
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
