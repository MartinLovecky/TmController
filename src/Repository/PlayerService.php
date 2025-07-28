<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Plugins\Panels;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\RepositoryManager;

class PlayerService
{
    public int $numSpecs = 0;
    public int $numPlayers = 0;

    public function __construct(
        protected Client $client,
        protected Container $container,
        protected Panels $panels,
        protected RepositoryManager $repository
    ) {
        $this->syncFromServer();
    }

    public function syncFromServer(): void
    {
        $count = 0;
        foreach ($this->getPlayerList() as $player) {
            $login = $player->get('Login');
            $info = $this->getDetailedPlayerInfo($login);
            // Game = TMU|TMF, Nation = XXX formant example CZE
            $info
                ->set('Game', Aseco::getGameName($info['OnlineRights']))
                ->set('Nation', Aseco::mapCountry($info['Path']))
                ->set('Donation', [100, 200, 500, 1000, 2000])
                ->set('panels.admin', $this->panels->adminPanel) //NOTE: after startup
                ->set('panels.donate', $this->panels->donatePanel)//NOTE: PUT STARTUP INTO __construct
                ->set('panels.records', $this->panels->recordsPanel)
                ->set('panels.vote', $this->panels->votePanel);

            $this->container->set($login, $info);
            $this->numSpecs = $this->container->get("$login.IsSpectator") === true ? ++$count : $count;
            //$this->container->set('NumSpecs', $NumSpecs);
            // this makes our life harder only way is "$login.NumSpecs" but it would lose usage
            $this->numPlayers = $this->container->count(); // also avaible on getAllPlayers()
        }

        $this->storeAllFromServer();
    }

    /**
     * @param Container<string, Container<string, mixed>> $players
    */
    public function update(Container $player): void
    {
        foreach ($player->getIterator() as $_ => $playerContainer) {
            $this->repository->update(Table::PLAYERS, $playerContainer);
            $this->repository->update(Table::PLAYERS_EXTRA, $playerContainer);
        }
    }

    public function delete(Container $player): void
    {
        foreach ($player->getIterator() as $login => $_) {
            $this->container->delete($login);
            $this->repository->delete(Table::PLAYERS, 'playerID', $login);
            $this->repository->delete(Table::PLAYERS_EXTRA, 'playerID', $login);
        }
    }

    public function first(): ?Container
    {
        return $this->container->first();
    }

    public function getAllLogins(): array
    {
        return $this->container->array_keys();
    }

    public function eachPlayer(callable $callback): void
    {
        foreach ($this->container as $player) {
            $callback($player);
        }
    }

    public function getAllPlayers(): Container
    {
        return $this->container;
    }

    public function getPlayerByLogin(string $login): ?Container
    {
        return $this->container->get($login);
    }

    public function hasPlayer(string $login): bool
    {
        return $this->container->has($login);
    }

    public function getCurrentRanking(): Container
    {
        return $this->client->query('GetCurrentRanking', [0, 100], true);
    }

    private function storeAllFromServer(): void
    {
        foreach ($this->container->getIterator() as $_ => $player) {
            $this->repository->insert(Table::PLAYERS, $player);
            $this->repository->insert(Table::PLAYERS_EXTRA, $player);
        }
    }

    private function getDetailedPlayerInfo(string $login): Container
    {
        return $this->client->query('GetDetailedPlayerInfo', [$login], false);
    }

    private function getPlayerList(): array
    {
        return $this->client->query('GetPlayerList', [30, 0, 2], true)->get('value');
    }
}
