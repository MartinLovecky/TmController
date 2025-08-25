<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Aseco, Server};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Rasp\{Vote, RaspState};
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\ChallengeService;

class RaspJukebox
{
    private array $buffer = [];
    private int $bufferSize = 0;

    public function __construct(
        protected ChallengeService $challengeService,
        protected Client $client,
        protected ManiaLinks $maniaLinks,
        protected RaspState $raspState,
    ) {
    }

    public function onSync(): void
    {
        $filePath = Server::$trackDir . 'trackhist.txt';

        if (!is_readable($filePath)) {
            throw new \RuntimeException("Failed to open track history file: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->buffer = array_slice($lines, -$this->bufferSize);
        array_pop($this->buffer);
    }

    public function chatY(TmContainer $player): void
    {
        $login  = $player->get('Login');

        if ($this->isSpectatorVoteNotAllowed($player, $login)) {
            return;
        }
        if ($this->hasAlreadyVoted($login)) {
            return;
        }

        if ($this->isTmxVoteActive()) {
            $this->processVote($vote, $login, function () {
                $this->passTmxVote();
            });
            return;
        }

        if ($this->isChatVoteActive()) {
            $this->processVote($this->raspState->chatvote, $login, function () {
                $this->passChatVote();
            });
            return;
        }

        $this->sendNoVoteMessage($login);
    }

    private function isSpectatorVoteNotAllowed($player, string $login): bool
    {
        if (!$this->raspState->allow_spec_voting && $player->get('IsSpectator') && !Aseco::isAnyAdmin($login)) {
            $this->sendPrivateMessage(Aseco::getChatMessage('no_spectators', 'rasp'), $login);
            return true;
        }
        return false;
    }

    private function hasAlreadyVoted(string $login): bool
    {
        if (in_array($login, $this->raspState->plrvotes)) {
            $this->sendPrivateMessage('{#server}> {#error}You have already voted!', $login);
            return true;
        }
        return false;
    }

    private function isTmxVoteActive(): bool
    {
        return !empty($this->raspState->tmxadd) && $this->raspState->tmxadd['votes'] >= 0;
    }

    private function isChatVoteActive(): bool
    {
        return !empty($this->raspState->chatvote) && $this->raspState->chatvote['votes'] >= 0;
    }

    private function processVote(Vote &$vote, string $login, callable $onPass): void
    {
        $vote['votes']--;
        if ($vote['votes'] > 0) {
            $this->sendVoteReminder($vote);
            $this->raspState->plrvotes[] = $login;
            $this->maniaLinks->votePanelOff($login);
        } else {
            $onPass();
            $this->maniaLinks->allVotePanelsOff();
        }
    }

    private function passTmxVote(): void
    {
        $this->addTrackToJukebox($this->raspState->tmxadd, true);
        $this->sendGlobalMessage(
            Aseco::formatText(
                Aseco::getChatMessage('jukebox_pass', 'rasp'),
                Aseco::stripColors($this->raspState->tmxadd['name'])
            )
        );
        $this->raspState->tmxadd = [];
        // TODO: fire onJukeboxChanged event
    }

    private function passChatVote(): void
    {
        $this->sendGlobalMessage(
            Aseco::formatText(
                Aseco::getChatMessage('vote_pass'),
                $this->raspState->chatvote['desc']
            )
        );

        match ($this->raspState->chatvote['type']) {
            0 => $this->endRound($this->raspState->chatvote['login']),
            1 => $this->restart($this->raspState->chatvote['login']),
            2 => $this->replay($this->raspState->chatvote['login']),
            3 => $this->skip($this->raspState->chatvote['login']),
            4 => $this->kick($this->raspState->chatvote['target']),
            6 => $this->ignore(),
            default => null
        };
    }

    private function sendVoteReminder(Vote $vote): void
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

        if ($this->raspState->feature_tmxadd) {
            if ($this->raspState->feature_votes) {
                $message .= ' Use {#highlite}$i/add <ID>{#error} or see {#highlite}$i/helpvote{#error} to start one.';
            } else {
                $message .= ' Use {#highlite}$i/add <ID>{#error} to start one.';
            }
        } elseif ($this->raspState->feature_votes) {
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
        $this->raspState->jukebox[$uid] = [
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

        $this->raspState->jukebox = array_reverse($this->raspState->jukebox, true);
        $this->raspState->jukebox[$uid] = [
            'FileName' => $chall->get('Filename'),
            'Name'     => $chall->get('Name'),
            'Env'      => $chall->get('Environment'),
            'Login'    => $this->raspState->chatvote['login'],
            'Nick'     => $this->raspState->chatvote['nick'],
            'source'   => $source,
            'tmx'      => false,
            'uid'      => $uid
        ];
        $this->raspState->jukebox = array_reverse($this->raspState->jukebox, true);
    }

    // Vote action methods
    private function endRound(string $login): void
    {
        Aseco::console('Vote by {1} forced round end!', $login);
        $this->client->query('ForceEndRound');
    }

    private function restart(string $login): void
    {
        if ($this->raspState->ladder_fast_restart) {
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
        Aseco::console('Vote by {1} kicked player {2}!', $this->raspState->chatvote['login'], $target);
    }

    private function ignore(): void
    {
        $this->client->query('Ignore', [$this->raspState->chatvote['target']]);
        if (!in_array($this->raspState->chatvote['target'], Server::$muteList)) {
            Server::$muteList[] = $this->raspState->chatvote['target'];
        }
        Aseco::console(
            'Vote by {1} ignored player {2}!',
            $this->raspState->chatvote['login'],
            $this->raspState->chatvote['target']
        );
    }
}
