<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Exception;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Infrastructure\Gbx\{Client, ChallMapFetcher};
use Yuhzel\TmController\Infrastructure\Tmx\InfoFetcher;
use Yuhzel\TmController\Repository\RepositoryManager;
use Yuhzel\TmController\Services\Server;

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
        $file = Server::$trackDir . $this->getCurrentChallengeInfo()->get('FileName');

        if (!file_exists($file)) {
            throw new Exception("Cant find {$file} | TMController need to be in Dedicated server folder");
        }

        $this->gbx->setXml(true);
        $this->gbx->processFile($file);
        $this->tmx->setData($this->gbx->UId, true);
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

    public function getChallenge(): TmContainer
    {
        return $this->getCurrentChallengeInfo();
    }

    public function fromDB(?string $uid = null): TmContainer
    {
        $uid = $uid ?? $this->getUid();

        return TmContainer::fromArray(
            $this->repository->fetch(Table::CHALLENGES, 'Uid', $uid)
        );
    }

    public function updateChallengeInDb(TmContainer $challange): void
    {
        $update = [
            'Name' => $challange->get('Name'),
            'Author' => $challange->get('Author')
        ];

        $this->repository->update(Table::CHALLENGES, $update, $challange->get('UId'));
    }

    public function deleteChallengeInDb(string $uid): void
    {
        $this->repository->delete(Table::CHALLENGES, 'Uid', $uid);
    }

    public function getGameMode(): string
    {
        $gameMode = $this->getChallenge()->get('Options.GameMode');
        return self::GAMEMODE_MAPPING[$gameMode] ?? self::DEFAULT_GAMEMODE;
    }

    public function getCurrentChallengeInfo(): TmContainer
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

    public function getNextChallengeInfo(): TmContainer
    {
        $c = $this->client->query('GetNextChallengeInfo');
        $c->set('FileName', str_replace('\\', DIRECTORY_SEPARATOR, $c->get('FileName')));
        return $c;
    }


    private function createChallengeInDb(): void
    {
        $data = [
            'Uid'         => $this->gbx->UId,
            'Name'        => $this->gbx->name,
            'Author'      => $this->gbx->author,
            'Environment' => $this->gbx->envir
        ];
        $this->repository->insert(Table::CHALLENGES, $data, $this->gbx->UId);
    }
}
