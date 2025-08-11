<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Plugins\Rasp;
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Services\Server;

class RaspJukebox extends Rasp
{
    private array $buffer = [];
    private int $bufferSize = 0;

    public function __construct(
        protected ChallengeService $challengeService,
        protected Client $client,
        protected ManiaLinks $maniaLinks,
    ) {
    }

    public function onSync(): void
    {
        $filePath = Aseco::path(3)
        . 'GameData'
        . DIRECTORY_SEPARATOR
        . 'Tracks'
        . DIRECTORY_SEPARATOR
        . 'trackhist.txt';

        if (!is_readable($filePath)) {
            throw new \RuntimeException("Failed to open track history file: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->buffer = array_slice($lines, -$this->bufferSize);
        array_pop($this->buffer);
    }
    public function chatY(array $command): void
    {
        $player = $command['author'];
        $login  = $player->get('Login');

        if ($this->isSpectatorVoteNotAllowed($player, $login)) {
            return;
        }
        if ($this->hasAlreadyVoted($login)) {
            return;
        }

        if ($this->isTmxVoteActive()) {
            $this->processVote($this->tmxadd, $login, function () {
                $this->passTmxVote();
            });
            return;
        }

        if ($this->isChatVoteActive()) {
            $this->processVote($this->chatvote, $login, function () {
                $this->passChatVote();
            });
            return;
        }

        $this->sendNoVoteMessage($login);
    }

    private function isSpectatorVoteNotAllowed($player, string $login): bool
    {
        if (!$this->allow_spec_voting && $player->get('IsSpectator') && !Aseco::isAnyAdmin($login)) {
            $this->sendPrivateMessage(Aseco::getChatMessage('no_spectators', 'rasp'), $login);
            return true;
        }
        return false;
    }

    private function hasAlreadyVoted(string $login): bool
    {
        if (in_array($login, $this->plrvotes)) {
            $this->sendPrivateMessage('{#server}> {#error}You have already voted!', $login);
            return true;
        }
        return false;
    }

    private function isTmxVoteActive(): bool
    {
        return !empty($this->tmxadd) && $this->tmxadd['votes'] >= 0;
    }

    private function isChatVoteActive(): bool
    {
        return !empty($this->chatvote) && $this->chatvote['votes'] >= 0;
    }

    private function processVote(array &$vote, string $login, callable $onPass): void
    {
        $vote['votes']--;
        if ($vote['votes'] > 0) {
            $this->sendVoteReminder($vote);
            $this->plrvotes[] = $login;
            $this->maniaLinks->votePanelOff($login);
        } else {
            $onPass();
            $this->maniaLinks->allVotePanelsOff();
        }
    }

    private function passTmxVote(): void
    {
        $this->addTrackToJukebox($this->tmxadd, true);
        $this->sendGlobalMessage(
            Aseco::formatText(
                Aseco::getChatMessage('jukebox_pass', 'rasp'),
                Aseco::stripColors($this->tmxadd['name'])
            )
        );
        $this->tmxadd = [];
        // TODO: fire onJukeboxChanged event
    }

    private function passChatVote(): void
    {
        $this->sendGlobalMessage(
            Aseco::formatText(
                Aseco::getChatMessage('vote_pass'),
                $this->chatvote['desc']
            )
        );

        match ($this->chatvote['type']) {
            0 => $this->endRound($this->chatvote['login']),
            1 => $this->restart($this->chatvote['login']),
            2 => $this->replay($this->chatvote['login']),
            3 => $this->skip($this->chatvote['login']),
            4 => $this->kick($this->chatvote['target']),
            6 => $this->ignore(),
            default => null
        };

        $this->chatvote = [];
    }

    private function sendVoteReminder(array $vote): void
    {
        $messageKey = isset($vote['tmx']) ? 'jukebox_y' : 'vote_y';
        $desc       = isset($vote['tmx']) ? Aseco::stripColors($vote['name']) : $vote['desc'];

        $message = Aseco::formatText(
            Aseco::getChatMessage($messageKey, 'rasp'),
            $vote['votes'],
            ($vote['votes'] == 1 ? '' : 's'),
            $desc
        );

        $this->sendGlobalMessage($message);
    }

    private function sendNoVoteMessage(string $login): void
    {
        $message = '{#server}> {#error}There is no vote right now!';

        if ($this->feature_tmxadd) {
            if ($this->feature_votes) {
                $message .= ' Use {#highlite}$i/add <ID>{#error} or see {#highlite}$i/helpvote{#error} to start one.';
            } else {
                $message .= ' Use {#highlite}$i/add <ID>{#error} to start one.';
            }
        } elseif ($this->feature_votes) {
            $message .= ' See {#highlite}$i/helpvote{#error} to start one.';
        }

        $this->sendPrivateMessage($message, $login);
    }

    private function sendGlobalMessage(string $message): void
    {
        $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
    }

    private function sendPrivateMessage(string $message, string $login): void
    {
        $this->client->query('ChatSendServerMessageToLogin', [Aseco::formatColors($message), $login]);
    }

    private function addTrackToJukebox(array $track, bool $isTmx = false): void
    {
        $uid = $track['uid'];
        $this->jukebox[$uid] = [
            'FileName' => $track['filename'],
            'Name'     => $track['name'],
            'Env'      => $track['environment'],
            'Login'    => $track['login'],
            'Nick'     => $track['nick'],
            'source'   => $track['source'] ?? ($isTmx ? 'TMX' : 'Unknown'),
            'tmx'      => $isTmx,
            'uid'      => $uid
        ];
    }

    private function jukeboxCurrentTrack(string $source): void
    {
        $uid   = $this->challengeService->getUid();
        $chall = $this->challengeService->getChallenge();

        $this->jukebox = array_reverse($this->jukebox, true);
        $this->jukebox[$uid] = [
            'FileName' => $chall->get('Filename'),
            'Name'     => $chall->get('Name'),
            'Env'      => $chall->get('Environment'),
            'Login'    => $this->chatvote['login'],
            'Nick'     => $this->chatvote['nick'],
            'source'   => $source,
            'tmx'      => false,
            'uid'      => $uid
        ];
        $this->jukebox = array_reverse($this->jukebox, true);
    }

// Vote action methods
    private function endRound(string $login): void
    {
        Aseco::console('Vote by {1} forced round end!', $login);
        $this->client->query('ForceEndRound');
    }

    private function restart(string $login): void
    {
        if ($this->ladder_fast_restart) {
            $this->client->query('ChallengeRestart');
        } else {
            $this->jukeboxCurrentTrack('Ladder');
            $this->client->query('NextChallenge');
            Aseco::console('Vote by {1} restarted track for ladder!', $login);
        }
    }

    private function replay(string $login): void
    {
        $this->jukeboxCurrentTrack('Replay');
        // TODO: fire onJukeboxChanged event
        Aseco::console('Vote by {1} replays track after finish!', $login);
    }

    private function skip(string $login): void
    {
        $this->client->query('NextChallenge');
        Aseco::console('Vote by {1} skips this track!', $login);
    }

    private function kick(string $target): void
    {
        $this->client->query('Kick', [$target]);
        Aseco::console('Vote by {1} kicked player {2}!', $this->chatvote['login'], $target);
    }

    private function ignore(): void
    {
        $this->client->query('Ignore', [$this->chatvote['target']]);
        if (!in_array($this->chatvote['target'], Server::$muteList)) {
            Server::$muteList[] = $this->chatvote['target'];
        }
        Aseco::console('Vote by {1} ignored player {2}!', $this->chatvote['login'], $this->chatvote['target']);
    }
}
