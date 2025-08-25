<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\ChatCmd;
use Yuhzel\TmController\Plugins\Manager\EventContext;
use Yuhzel\TmController\Plugins\Manager\PageListManager;
use Yuhzel\TmController\Plugins\RaspJukebox;
use Yuhzel\TmController\Repository\ChallengeService;

class RaspHelper
{
    private $challengeListCache = [];

    public function __construct(
        protected ChatCmd $chatCmd,
        protected ChallengeService $challengeService,
        protected PageListManager $pageListManager,
        protected EventContext $eventContext,
    ) {
    }

    public function getAllChallenges(TmContainer $player, string $wildcard, string $env)
    {
        $this->pageListManager->setPages([], [
            'title'  => 'Tracks On This Server',
            'layout' => (SERVER::$packmask !== 'Stadium')
                ? [1.39, 0.12, 0.1, 0.6, 0.4, 0.17]
                : [1.22, 0.12, 0.1, 0.6, 0.4],
            'icon'   => ['Icons128x128_1', 'NewTrack', 0.02],
        ]);

        $rec = $this->chatCmd->getRecs();
        $jb_buffer = $this->eventContext->data[RaspJukebox::class]->buffer;

        if (!$rec->isEmpty()) {
            $newlist = $this->getChallengesCache();
            $tid   = 1;
            $lines = 0;
            $headerRow = (SERVER::$packmask !== 'Stadium')
                ? ['Id','Rec','Name','Author','Env']
                : ['Id','Rec','Name','Author'];

            $currentPage = [$headerRow];
            $envids = [
                'Stadium' => 11,
                'Alpine'  => 12,
                'Bay'     => 13,
                'Coast'   => 14,
                'Island'  => 15,
                'Rally'   => 16,
                'Speed'   => 17
            ];

            foreach ($newlist as $row) {
                $match = (
                    $wildcard === '*'
                    || stripos(Aseco::stripColors($row['Name']), $wildcard) !== false
                    || stripos($row['Author'], $wildcard) !== false);
                $envMatch = ($env === '*' || stripos($row['Environnement'], $env) !== false);

                if (!$match || !$envMatch) {
                    continue;
                }

                $this->pageListManager->addTrack([
                    'name'        => $row['Name'],
                    'author'      => $row['Author'] ?? null,
                    'environment' => $row['Environnement'],
                    'filename'    => $row['FileName'],
                    'uid'         => $row['UId'],
                ]);

                $trackname = Aseco::stripColors($row['Name']);
                $trackname = in_array($row['UId'], $jb_buffer) ? '{#grey}' . $trackname : '{#black}' . $trackname;

                if (Aseco::getSetting('clickable_lists') && $tid <= 1900 && !in_array($row['UId'], $jb_buffer)) {
                    $trackname = [$trackname, $tid + 100];
                }
            }
        }
    }

    private function getChallengesCache(): array
    {
        if (!$this->challengeListCache) {
            $newlist = [];
            $done = false;
            $size = 300;
            $i = 0;

            while (!$done) {
                $tracks = $this->challengeService->getChallengeList($size, $i);
                foreach ($tracks as $throw) {
                    $trackInfo = $this->getChallengeData(Server::$trackDir . $throw['FileName']);
                    if ($trackInfo['name'] != 'file not found') {
                        $trow['Author'] = $trackInfo['author'];
                        $trow['AuthorTime']  = $trackInfo['authortime'];
                        $trow['AuthorScore'] = $trackInfo['authorscore'];
                        $newlist[$trow['UId']] = $trow;
                    }
                }
                if (count($tracks) < $size) {
                    $done = true;
                } else {
                    $i += $size;
                }
            }

            $this->challengeListCache = $newlist;
        }

        return $this->challengeListCache;
    }

    private function getChallengeData(string $fileName): array
    {
        $ret = [];

        if (!file_exists($fileName)) {
            $ret['name'] = 'file not found';
            $ret['votes'] = 500;
            return $ret;
        }

        $gbx = $this->challengeService->getGBX();
        $gbx->processFile($fileName);
        $ret['uid'] = $gbx->UId;
        $ret['name'] = str_replace(
            ["\n\n", "\r", "\n"],
            [' ', '', ''],
            $gbx->name
        );
        $ret['author'] = $gbx->author;
        $ret['environment'] = $gbx->envir;
        $ret['authortime'] = $gbx->authorTime;
        $ret['authorscore'] = $gbx->authorScore;
        $ret['coppers'] = $gbx->cost;

        return $ret;
    }
}
