<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Services\Karma\StateService;
use Yuhzel\TmController\Services\RaspState;

class RaspVotes
{
    private int $num_laddervotes = 0;
    private int $num_replayvotes = 0;
    private int $num_skipvotes = 0;
    private float $ta_show_num = 0;
    private bool $auto_vote_starter = true;
    private bool $allow_spec_startvote = false;
    private array $r_expire_limit = [1, 2, 3, 2, 3, 3, 3];
    private bool $r_show_reminder = false;
    private array $ta_expire_limit = [0, 90, 120, 90, 120, 120, 120];
    private bool $ta_show_reminder = true;
    private int $ta_show_interval = 240;
    private int $global_explain = 2;
    private array $voteRatios = [0.4, 0.5, 0.6, 0.6, 0.7, 1.0, 0.6];
    private bool $vote_in_window = true;
    private bool $disable_upon_admin = false;
    private bool $disable_while_sb = true;
    private bool $allow_kickvotes = false;
    private bool $allow_admin_kick = false;
    private bool $allow_ignorevotes = false;
    private bool $allow_admin_ignore = false;
    private int $max_laddervotes = 0;
    private int $max_replayvotes = 2;
    private int $max_skipvotes = 1;
    private int $replays_limit = 2;
    private bool $r_points_limits = false;
    private float $r_ladder_max = 0.4;
    private float $r_skip_max = 0.5;
    private bool $ta_time_limits = false;
    private int $ta_ladder_max = 0;
    private float $ta_replay_min = 0.2;
    private float $ta_skip_max = 1.0;
    private int $replays_counter = 0;
    private bool $disabled_scoreboard = true;

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected ManiaLinks $maniaLinks,
        protected RaspJukebox $raspJukebox,
        protected RaspState $raspState,
        protected StateService $stateService,
        protected Track $track,
    ) {
    }

    public function onSync(): void
    {
        // Array of structs [[[]]]
        // Struct in array [[]]
        $this->client->query('SetCallVoteRatios', [[['Command' => '*', 'Ratio' => -1.0]]]);
        $this->resetVotes();
    }

    public function onEndRace1(): void
    {
        $this->resetVotes();
    }

    public function onNewChallenge2(): void
    {
        $this->disabled_scoreboard = false;
    }

    public function onPlayerConnect(Container $player): void
    {
        if (Aseco::$startupPhase) {
            return;
        }

        if ($this->raspState->feature_votes) {
            $message = Aseco::getChatMessage('vote_explain', 'rasp');
            if ($this->global_explain === 2) {
                $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
            } elseif ($this->global_explain === 1) {
                $message = str_replace('{#server}>> ', '{#server}> ', $message);
                $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message),
                $player->get('Login')
                ]);
            }
        }
    }

    public function onPlayerDisconnect(Container $player): void
    {
        if ($this->raspState->feature_votes && !empty($this->raspState->chatvote)) {
            if (
                $this->raspState->chatvote['type'] === 4
                && $this->raspState->chatvote['target'] === $player->get('Login')
            ) {
                $this->resetVotes($player);
            }
        }
    }

    public function onEndRound(): void
    {
        if ($this->challengeService->getGameMode() !== 'rounds') {
            return;
        }
        if (!empty($this->raspState->chatvote) && $this->raspState->chatvote['type'] === 0) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('vote_end', 'rasp'),
                $this->raspState->chatvote['desc'],
                'expired',
                'Server'
            );
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
            $this->raspState->chatvote = [];
            $this->maniaLinks->allVotePanelsOff();
        }
    }

    public function onCheckpoint(): void
    {
        if (
            $this->challengeService->getGameMode() === 'rounds'
            || $this->challengeService->getGameMode() === 'team'
            || $this->challengeService->getGameMode() === 'cup'
        ) {
            return;
        }

        if (!empty($this->raspState->chatvote)) {
            $expire_limit = !empty($this->raspState->tmxadd)
                ? $this->ta_expire_limit[5]
                : $this->ta_expire_limit[$this->raspState->chatvote['type']];
            $played = $this->track->timePlaying();
            if ($played - $this->track->timePlaying() >= $expire_limit) {
                Aseco::console(
                    'Vote by {1} to {2} expired!',
                    $this->raspState->chatvote['login'],
                    $this->raspState->chatvote['desc']
                );
                $message = Aseco::formatText(
                    Aseco::getChatMessage('vote_end', 'rasp'),
                    $this->raspState->chatvote['desc'],
                    'expired',
                    'Server'
                );
                $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
                $this->raspState->chatvote = [];
                $this->maniaLinks->allVotePanelsOff();
            } else {
                if ($this->ta_show_reminder) {
                    $intervals = floor($played - $this->track->timePlaying() / $this->ta_show_interval);
                    if ($intervals > $this->ta_show_num) {
                        $this->ta_show_num = $intervals;
                        $message = Aseco::formatText(
                            Aseco::getChatMessage('vote_y', 'rasp'),
                            $this->raspState->chatvote['votes'],
                            $this->raspState->chatvote['votes'] == 1 ? '' : 's',
                            $this->raspState->chatvote['desc']
                        );
                        $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
                    }
                }
            }
        }

        if (!empty($this->raspState->tmxadd)) {
            Aseco::console(
                'Vote by {1} to add {2} expired!',
                $this->raspState->tmxadd['login'],
                Aseco::stripColors($this->raspState->tmxadd['name'], false)
            );
            $message = Aseco::formatText(
                Aseco::getChatMessage('jukebox_end', 'rasp'),
                Aseco::stripColors($this->raspState->tmxadd['name']),
                'expired',
                'Server'
            );

            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
        }

        $this->raspState->chatvote = [];
        $this->maniaLinks->allVotePanelsOff();
    }

    public function chatHelpvote($command): void
    {
        $login = $command['author']->login;
        if (!$this->raspState->feature_votes) {
            $message = Aseco::getChatMessage('no_vote', 'rasp');
            $this->client->query('ChatSendServerMessageToLogin', [Aseco::formatColors($message), $login]);
        }
        $header = '{#vote}Chat-based votes$g are available for these actions:';
        $data = [];
        $data[] = ['Ratio', '{#black}Command', '',];
        $data[] = [$this->voteRatios[0] * 100 . '%', '{#black}/endround',
            'Starts a vote to end current round'];
        $data[] = [$this->voteRatios[1] * 100 . '%', '{#black}/ladder',
            'Starts a vote to end current for ladder'];
        $data[] = [$this->voteRatios[2] * 100 . '%', '{#black}/replay',
            'Starts a vote to play this track again'];
        $data[] = [$this->voteRatios[3] * 100 . '%', '{#black}/skip',
            'Starts a vote to skip this track'];

        if ($this->allow_ignorevotes) {
            $data[] = [$this->voteRatios[6] * 100 . '%', '{#black}/ignore',
                'Starts a vote to ignore a player'];
        }
        if ($this->allow_kickvotes) {
            $data[] = [$this->voteRatios[4] * 100 . '%', '{#black}/kick',
                'Starts a vote to kick a player'];
        }
        $data[] = ['', '{#black}/cancel', 'Cancels your current vote'];
        $data[] = [];
        $data[] = ['Players can vote with {#black}/y$g until the required number of votes'];
        $data[] = ['is reached, or the vote expires.'];

        $this->maniaLinks->displayManialink(
            $login,
            $header,
            ['Icons64x64_1', 'TrackInfo', -0.01],
            $data,
            [1.0, 0.1, 0.2, 0.7],
            'OK'
        );
    }

    public function chatEndround($command): void
    {
        $player = $command['author'];
        $login = $player->get('Login');

        if (!$this->raspState->feature_votes) {
            $message = Aseco::formatColors(Aseco::getChatMessage('no_vote', 'rasp'));
            $this->client->query('ChatSendServerMessageToLogin', [$message, $login]);
            return;
        }
        if ($this->disabled_scoreboard) {
            $message = Aseco::formatColors(Aseco::getChatMessage('no_sb_vote', 'rasp'));
            $this->client->query('ChatSendServerMessageToLogin', [$message, $login]);
            return;
        }
        if ($this->allow_spec_startvote && $player->get('isSpectator')) {
            $message = Aseco::formatColors(Aseco::getChatMessage('no_spectators', 'rasp'));
            $this->client->query('ChatSendServerMessageToLogin', [$message, $login]);
            return;
        }
        //TODO: admin_online
        if ($this->disable_upon_admin) {
            DD("TODO: Rasp votes -> chatEndround");
            $message = Aseco::formatColors(Aseco::getChatMessage('ask_admin', 'rasp'));
            $this->client->query('ChatSendServerMessageToLogin', [$message, $login]);
            return;
        }
        if (!empty($this->raspState->chatvote) || !empty($this->raspState->tmxadd)) {
            $message = Aseco::formatColors(Aseco::getChatMessage('vote_already', 'rasp'));
            $this->client->query('ChatSendServerMessageToLogin', [$message, $login]);
            return;
        }

        if (
            $this->challengeService->getGameMode() == 'time_attack'
            || $this->challengeService->getGameMode() == 'laps'
            || $this->challengeService->getGameMode() == 'stunts'
        ) {
            $message = "{#server}> {#error}Running {#highlite}\$i{$this->challengeService->getGameMode()}";
            $message .= "{#error} mode - end round disabled!";
            $this->client->query('ChatSendServerMessageToLogin', [$message, $login]);
            return;
        }

        $this->raspState->chatvote['login'] = $login;
        $this->raspState->chatvote['nick'] = $player->get('Nickname');
        $this->raspState->chatvote['votes'] = $this->requiredVotes($this->voteRatios[0]);
        $this->raspState->chatvote['type'] = 0;
        $this->raspState->chatvote['desc'] = 'End this Round';
        $this->raspState->plrvotes = [];

        $message = Aseco::formatText(
            Aseco::getChatMessage('vote_start', 'rasp'),
            Aseco::stripColors($this->raspState->chatvote['nick']),
            $this->raspState->chatvote['desc'],
            $this->raspState->chatvote['votes']
        );
        $message = str_replace('{br}', "\n", $message);
        $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
        //TODO: Only for admin
        //$this->maniaLinks->displayVotePanel($player, Aseco::formatColors('{#vote}Yes'), '$333No');
        $this->maniaLinks->displayVotePanel($player, Aseco::formatColors('{#vote}Yes - F5'), '$333No');

        if ($this->auto_vote_starter) {
            $this->raspJukebox->chatY($command);
        }
    }

    public function chatLadder($command)
    {
    }

    private function resetVotes(?Container $player = null): void
    {
        if (!empty($this->raspState->chatvote)) {
            Aseco::console(
                'Vote by {1} to {2} reset!',
                $player->get('Login') ?? $this->raspState->chatvote['login'] ?? '',
                'End this Round'
            );
        }

        $message = Aseco::getChatMessage('vote_cancel', 'rasp');
        $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
        $this->raspState->chatvote = [];
        $this->maniaLinks->allVotePanelsOff();
        $this->num_laddervotes = 0;
        $this->num_replayvotes = 0;
        $this->num_skipvotes = 0;
    }

    private function requiredVotes(float $ratio)
    {
        dd("TODO: RaspVotes -> requiredVotes");
    }

    private function activePlayers()
    {
        dd("TODO: RaspVotes -> activePlayers");
    }
}
