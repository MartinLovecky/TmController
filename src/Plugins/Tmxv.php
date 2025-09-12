<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Aseco, HttpClient, Sender};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Manager\PageListManager;
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\Repository\ChallengeService;

class Tmxv
{
    private const VIDEOS_PER_PAGE = 16;
    private const YOUTUBE_URL = 'http://youtu.be/';

    public array $videos = [];

    public function __construct(
        private ChallengeService $challengeService,
        private HttpClient $httpClient,
        private PageListManager $pageListManager,
        private ManiaLinks $manialinks,
        private Sender $sender,
    ) {}

    public function onNewChallenge(): void
    {
        $tmx = $this->challengeService->getTMX();
        if ($tmx->id <= 0) {
            return;
        }

        Aseco::console('Requesting videos for track with TMX ID {1}', $tmx->id);

        $this->httpClient->setTimeout(10);
        $output = $this->httpClient->get('https://tmnf.exchange/api/videos', [
            'fields'  => 'LinkId,Title,PublishedAt',
            'trackid' => $tmx->id,
        ]);

        if (!$output) {
            return;
        }

        $result = Aseco::safeJsonDecode($output, true);
        if (!is_array($result) || empty($result['Results'])) {
            return;
        }

        $this->videos = $result['Results'];
        $this->sortVideosByPublishedDate($this->videos);

        Aseco::console('Found {1} videos for TMXID {2}', count($this->videos), $tmx->id);
    }

    public function handleChatCommand(TmContainer $player): void
    {
        if ($player->get('command.name') !== 'tmxv') {
            return;
        }
        // name: tmxv?'' || params: gps?'' || args: list?''
        $params = $player->get('command.params');
        $args = $player->get('command.arg');

        $login = $player->get('Login');

        match ($params) {
            'videos' => $this->showVideos($login),
            'video', 'gps' => $this->handleArgs($login, $args),
            default => $this->unknownArg($login),
        };
    }

    private function showVideos(string $login): void
    {
        $this->hasVideos()
            ? $this->showVideosManialink($login)
            : $this->noVideos($login);
    }

    private function handleArgs(string $login, string $arg): void
    {
        match ($arg) {
            'help'   => $this->showHelpManialink($login),
            'latest' => $this->sendLatestVideo($login),
            'oldest' => $this->sendOldestVideo($login),
            'list'   => $this->showVideos($login),
            ''       => $this->sendLatestVideo($login),
            default  => $this->unknownArg($login),
        };
    }

    private function showHelpManialink(string $login): void
    {
        $header = '$000/gps$g will give the latest video in chat:';
        $help = [
            ['...', '$000list',   'Gives all videos in a window'],
            ['...', '$000latest', 'Gives the latest video in chat'],
            ['...', '$000oldest', 'Gives the oldest video in chat'],
            ['...', '$000help',   'Shows this help window'],
            [],
            ['$000/videos', '', 'Same as /gps list'],
            ['$000/video',  '', 'Same as /gps'],
        ];

        $this->manialinks->displayManialink(
            $login,
            $header,
            ['Icons64x64_1', 'TrackInfo', -0.01],
            $help,
            [1.1, 0.05, 0.3, 0.75],
            'OK'
        );
    }

    private function unknownArg(string $login): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: "\$ff0>Unknown command, use /gps help for more information.",
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function sendLatestVideo(string $login): void
    {
        $this->sendVideoIfExists($login, 0);
    }

    private function sendOldestVideo(string $login): void
    {
        $this->sendVideoIfExists($login, count($this->videos) - 1);
    }

    private function sendVideoIfExists(string $login, int $id): void
    {
        if (!$this->hasVideos() || !isset($this->videos[$id])) {
            $this->noVideos($login);
            return;
        }

        $title = $this->escapeTitle($this->videos[$id]['Title']);
        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: "\$ff0>YouTube \$l[" . self::YOUTUBE_URL . "{$this->videos[$id]['LinkId']}]{$title}\$l.",
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function noVideos(string $login): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $login,
            message: "\$ff0>No GPS videos found for this track.",
            formatMode: Sender::FORMAT_NONE
        );
    }

    private function showVideosManialink(string $login): void
    {
        $challenge = $this->challengeService->getChallenge();
        $header = "Videos for {$challenge->get('Name')}";

        $pages = $this->buildVideoPages();

        $this->pageListManager->setPages($login, $pages, [
            'index'  => 1,
            'header' => $header,
            'widths' => [1],
            'icon'   => ['Icons64x64_1', 'TrackInfo']
        ]);

        $this->manialinks->eventManiaLink(TmContainer::fromArray([
            'message' => 1,
            'login'   => $login
        ]));
    }

    private function buildVideoPages(): array
    {
        $pages = [];
        $currentPage = [];

        foreach ($this->videos as $index => $video) {
            $title = $this->escapeTitle($video['Title']);
            $currentPage[] = ["\$l[" . self::YOUTUBE_URL . "{$video['LinkId']}]{$title}"];

            if (($index + 1) % self::VIDEOS_PER_PAGE === 0) {
                $pages[] = $currentPage;
                $currentPage = [];
            }
        }

        if (!empty($currentPage)) {
            $pages[] = $currentPage;
        }

        return $pages;
    }

    private function hasVideos(): bool
    {
        return !empty($this->videos);
    }

    private function escapeTitle(string $title): string
    {
        return str_replace('$', '$$', $title);
    }

    private function sortVideosByPublishedDate(array &$videos): void
    {
        usort($videos, fn($a, $b) => strtotime($b['PublishedAt']) <=> strtotime($a['PublishedAt']));
    }
}
