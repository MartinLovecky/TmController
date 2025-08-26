<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

use Yuhzel\TmController\Plugins\Manager\{PageListManager};
use Yuhzel\TmController\Plugins\RaspJukebox;
use Yuhzel\TmController\Repository\{ChallengeService, RecordService};

class RaspHelper
{
    private $challengeListCache = [];
    private const MAX_ACTION_ID = 1900;
    private const MAX_LINES_PER_PAGE = 15;
    protected ?RaspJukebox $raspJukebox = null;


    public function __construct(
        protected ChallengeService $challengeService,
        protected PageListManager $pageListManager,
        protected RecordService $recordService,
    ) {
    }

    public function setDependecy($registry): void
    {
        $this->raspJukebox = $registry;
    }

    /**
     * Show all Stadium challenges, optionally filtered by name/author.
     *
     * @param string $login
     * @param string $search
     * @return void
     */
    public function getAllChallenges(string $login, string $search = '*'): void
    {
        $this->pageListManager->setPages($login, [], [
            'title'  => 'Stadium Tracks',
            'layout' => [1.22, 0.12, 0.1, 0.6, 0.4],
            'icon'   => ['Icons128x128_1', 'NewTrack', 0.02],
        ]);

        $tracklist = $this->getChallengesCache();
        $uids      = array_column($tracklist, 'UId');

        // Fetch top times for all Stadium tracks
        $recordsByUid = $this->recordService->getTopTimes($uids);
        $jbBuffer     = $this->raspJukebox->buffer ?? [];

        $headerRow = ['Id', 'Rec', 'Name', 'Author'];
        $allRows   = [];
        $trackId   = 1;

        foreach ($tracklist as $row) {
            if (($row['Environnement'] ?? '') !== 'Stadium') {
                continue;
            }

            if (!$this->matchesSearch($row, $search)) {
                continue;
            }

            $uid = $row['UId'];

            $this->pageListManager->addTrack($login, [
                'name'        => $row['Name'],
                'author'      => $row['Author'] ?? null,
                'environment' => $row['Environnement'],
                'filename'    => $row['FileName'],
                'uid'         => $uid,
            ]);

            $trackName   = $this->formatTrackName($row['Name'], $uid, $jbBuffer, $trackId);
            $trackAuthor = $this->formatAuthor($row['Author'] ?? 'Unknown', $trackId);

            // Calculate actual leaderboard rank
            $pos = '-- ';
            if (!empty($recordsByUid[$uid])) {
                $topTime = $recordsByUid[$uid][0]['time'] ?? null;
                if ($topTime !== null) {
                    // The rank is the index of the top time + 1
                    $rank = 1;
                    foreach ($recordsByUid[$uid] as $idx => $entry) {
                        if ($entry['time'] === $topTime) {
                            $rank = $idx + 1;
                            break;
                        }
                    }
                    $pos = str_pad((string)$rank, 2, '0', STR_PAD_LEFT);
                }
            }

            $allRows[] = [
                sprintf('%03d.%s.', $trackId, $pos),
                $trackName,
                $trackAuthor,
            ];

            $trackId++;
        }

        // Pagination
        foreach (array_chunk($allRows, self::MAX_LINES_PER_PAGE) as $pageRows) {
            $this->pageListManager->addPage($login, array_merge([$headerRow], $pageRows));
        }
    }

    /**
     * Check if track matches search string (name or author).
     *
     * @param array $row
     * @param string $search
     * @return boolean
     */
    private function matchesSearch(array $row, string $search): bool
    {
        if ($search === '*') {
            return true;
        }

        $name   = Aseco::stripColors($row['Name'] ?? '');
        $author = $row['Author'] ?? '';

        return stripos($name, $search) !== false || stripos($author, $search) !== false;
    }

    /**
     * Format track name for display (color + clickable id if enabled).
     *
     * @param string $name
     * @param string $uid
     * @param array $jbBuffer
     * @param integer $trackId
     * @return string|array
     */
    private function formatTrackName(string $name, string $uid, array $jbBuffer, int $trackId): string|array
    {
        $trackName = Aseco::stripColors($name);
        $inHistory = in_array($uid, $jbBuffer, true);

        $colorCode = $inHistory ? '{#grey}' : '{#black}';
        $trackName = $colorCode . $trackName;

        if (!$inHistory && Aseco::getSetting('clickable_lists') && $trackId <= self::MAX_ACTION_ID) {
            return [$trackName, $trackId + 100];
        }

        return $trackName;
    }

    /**
     * Format track author for display (clickable if enabled).
     *
     * @param string $author
     * @param integer $trackId
     * @return string|array
     */
    private function formatAuthor(string $author, int $trackId): string|array
    {
        if (Aseco::getSetting('clickable_lists') && $trackId <= self::MAX_ACTION_ID) {
            return [$author, -100 - $trackId];
        }

        return $author;
    }

    /**
     *
     * @return array
     */
    private function getChallengesCache(): array
    {
        if ($this->challengeListCache) {
            return $this->challengeListCache;
        }

        $cache = [];
        $size = 300;
        $i = 0;
        $done = false;

        while (!$done) {
            $tracks = $this->challengeService->getChallengeList($size, $i);
            foreach ($tracks as $track) {
                $info = $this->getChallengeData(Server::$trackDir . $track['FileName']);
                if ($info['name'] !== 'file not found') {
                    $cache[$info['uid']] = [
                        'UId'           => $info['uid'],
                        'Name'          => $info['name'],
                        'Author'        => $info['author'],
                        'Environnement' => $info['environment'],
                        'AuthorTime'    => $info['authortime'],
                        'AuthorScore'   => $info['authorscore'],
                        'FileName'      => $track['FileName'],
                        'Coppers'       => $info['coppers'],
                    ];
                }
            }

            if (count($tracks) < $size) {
                $done = true;
            } else {
                $i += $size;
            }
        }

        $this->challengeListCache = $cache;
        return $cache;
    }

    /**
     * gets gbx data in array
     *
     * @param string $fileName
     * @return array
     */
    private function getChallengeData(string $fileName): array
    {
        if (!file_exists($fileName)) {
            return [
            'uid' => '',
            'name' => 'file not found',
            'author' => 'unknown',
            'environment' => '',
            'authortime' => 0,
            'authorscore' => 0,
            'coppers' => 0,
            ];
        }

        $gbx = $this->challengeService->getGBX();
        $gbx->processFile($fileName);

        return [
            'uid' => $gbx->UId,
            'name' => str_replace(["\n\n", "\r", "\n"], ' ', $gbx->name),
            'author' => $gbx->author,
            'environment' => $gbx->envir,
            'authortime' => $gbx->authorTime,
            'authorscore' => $gbx->authorScore,
            'coppers' => $gbx->cost,
        ];
    }
}
