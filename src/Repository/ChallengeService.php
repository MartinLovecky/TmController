<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Exception;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Infrastructure\Gbx\{Client, ChallMapFetcher};
use Yuhzel\TmController\Infrastructure\Tmx\InfoFetcher;
use Yuhzel\TmController\Repository\RepositoryManager;

class ChallengeService
{
    private const GAMEMODE_MAPPING = [
        0 => 'rounds',
        1 => 'time_attack',
        2 => 'team',
        3 => 'laps',
        4 => 'stunts',
        5 => 'cup',
        7 => 'score'
    ];
    private const DEFAULT_GAMEMODE = 'time_attack';

    public function __construct(
        private ChallMapFetcher $gbx, //info from {FileName}.gbx
        private InfoFetcher $tmx,     //info from tmnf.exchange website
        protected Client $client,
        protected GameInfo $gameInfo,
        protected RepositoryManager $repository
    ) {
        $file = Aseco::path(3)
        . 'GameData'
        . DIRECTORY_SEPARATOR
        . 'Tracks'
        . DIRECTORY_SEPARATOR
        . $this->getCurrentChallengeInfo()->get('FileName');

        if (!file_exists($file)) {
            throw new Exception("Cant find {$file} | TMController need to be in Dedicated server folder");
        }

        $this->gbx->setXml(true);
        $this->gbx->processFile($file);
        $this->tmx->setData($this->getUid(), true);
        $this->createChallengeInDb();
    }

    public function getGBX(): ChallMapFetcher
    {
        return $this->gbx;
    }

    public function getTMX(): InfoFetcher
    {
        return $this->tmx;
    }

    public function getChallenge(): Container
    {
        return $this->getCurrentChallengeInfo();
    }

    public function fromDB(?string $uid = null)
    {
        $uid = $uid ?? $this->getUid();

        if (!$this->exists($uid)) {
            return;
        }

        return Container::fromArray(
            $this->repository->get(Table::CHALLENGES, 'Uid', $uid)
        );
    }

    public function updateChallengeInDb(Container $challange): void
    {
        if (!$this->exists($challange->get('UId'))) {
            return;
        }

        $this->repository->update(Table::CHALLENGES, $challange);
    }

    public function deleteChallengeInDb(string $uid): void
    {
        if (!$this->exists($uid)) {
            return;
        }

        $this->repository->delete(Table::CHALLENGES, 'Uid', $uid);
    }

    public function getGameMode(): string
    {
        $gameMode = $this->getChallenge()->get('Options.GameMode');
        return self::GAMEMODE_MAPPING[$gameMode] ?? self::DEFAULT_GAMEMODE;
    }

    public function getCurrentChallengeInfo(): Container
    {
        $c = $this->client->query('GetCurrentChallengeInfo');
        // Windows|Linux path fix
        $c
            ->set('FileName', str_replace('\\', DIRECTORY_SEPARATOR, $c->get('FileName')))
            ->set('Options', $this->gameInfo->getCurrentGameInfo());
        return $c;
    }

    public function getUid(): string
    {
        return $this->getCurrentChallengeInfo()->get('UId');
    }

    public function getNextUid(): string
    {
        $next = $this->client->query('GetNextChallengeIndex')->get('value');
        return $this->client->query('GetChallengeList', [1, $next])->get('value.0.UId');
    }

    public function getNextChallengeInfo(): Container
    {
        $c = $this->client->query('GetNextChallengeInfo');
        $c->set('FileName', str_replace('\\', DIRECTORY_SEPARATOR, $c->get('FileName')));
        return $c;
    }

    private function exists(string $uid): bool
    {
        return $this->repository->exists(Table::CHALLENGES, 'Uid', $uid);
    }

    private function createChallengeInDb(): void
    {
        if ($this->exists($this->getUid())) {
            return;
        }

        $this->repository->insert(Table::CHALLENGES, $this->getCurrentChallengeInfo());
    }
}
