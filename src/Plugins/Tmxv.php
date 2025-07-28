<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\App\Command;
use Yuhzel\TmController\Repository\ChallengeService;
use Yuhzel\TmController\Services\HttpClient;

class Tmxv
{
    public array $videos = [];
    private array $commands = [];

    public function __construct()
    {
        $this->commands = [
            ['videos',  [$this, 'videos'], 'Sets up the tmx videos command environment'],
            ['video',  [$this, 'video'], 'Gives latest video in chat'],
            ['gps',  [$this, 'gps'], 'Gives latest video in chat']
        ];
        Aseco::console('Plugin TMX Video initialized.');
        Command::register($this->commands, 'Tmxv');
    }

    public function onNewChallenge(ChallengeService $challengeService): void
    {
        $tmx = $challengeService->getTMX();
        if (!$tmx->id > 0) {
            return;
        }

        Aseco::console('Requesting videos for track with TMX ID {1}', $tmx->id);

        $httpClient = new HttpClient();
        $httpClient->setTimeout(10);
        $output = $httpClient->get('https://tmnf.exchange/api/videos', [
            'fields' => 'LinkId,Title,PublishedAt',
            'trackid' => $tmx->id
        ]);

        if (!$output) {
            return;
        }

        $result = Aseco::safeJsonDecode($output, true);

        if (!is_array($result)) {
            return;
        }

        if (isset($result['Results'])) {
            $this->videos = $result['Results'];
            $this->sortVideosByPublishedDate($this->videos);
        }

        Aseco::console('Found {1} videos for TMXID {2}', count($this->videos), $tmx->id);
    }

    private function sortVideosByPublishedDate(array &$videos): void
    {
        usort($videos, fn($a, $b) => strtotime($b['PublishedAt']) - strtotime($a['PublishedAt']));
    }
}
