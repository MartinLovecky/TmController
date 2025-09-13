<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\RepositoryManager;

class PlayerService
{
    public int $numSpecs = 0;
    public int $numPlayers = 0;

    public function __construct(
        private Client $client,
        private TmContainer $container,
        private RepositoryManager $repository
    ) {
    }

    /**
     * Add a player (used on PlayerConnect or startup).
     */
    public function addPlayer(string $login, bool $isMasterAdmin = false): void
    {
        // Avoid duplicates
        if ($this->hasPlayer($login)) {
            return;
        }

        /** @var TmContainer $info */
        $info = $this->getDetailedPlayerInfo($login);
        $info
            ->set('Nation', Aseco::mapCountry($info['Path']))
            ->set('created', time());

        if ($isMasterAdmin) {
            $info->set('isMasterAdmin', true);
        }

        $this->container->set($login, $info);

        $this->numSpecs = $this->container->get("$login.IsSpectator") === true
            ? $this->numSpecs + 1
            : $this->numSpecs;

        $this->numPlayers = $this->container->count();

        // Save to DB only if not exist
        $this->repository->insert(Table::PLAYERS, $this->playerData($info), $login);
        $this->repository->insert(Table::PLAYERS_EXTRA, $this->extraPlayerData($login), $login);
    }

    public function removePlayer(TmContainer $player, bool $fromDB = false): void
    {
        $login = $player->get('Login');

        if ($player->has('isMasterAdmin') || !$this->hasPlayer($login)) {
            return;
        }

        $this->container->delete($login);

        if ($fromDB) {
            $this->deleteFromDB($login);
        }
    }

    public function getActivePlayersCount(bool $includeSpectators = false): int
    {
        if ($includeSpectators) {
            return $this->numPlayers;
        }

        return $this->numPlayers - $this->numSpecs;
    }

    /**
     * Sync currently connected players (after restart).
     */
    public function syncFromServer(): void
    {
        foreach ($this->getPlayerList() as $player) {
            $login = $player->get('Login');
            $this->addPlayer($login);
        }
    }

    /**
     * Update player info in DB.
     * @param TmContainer<string, TmContainer<string, mixed>> $players
     */
    public function update(TmContainer $player): void
    {
        foreach ($player->getIterator() as $login => $player) {
            $this->repository->update(Table::PLAYERS, $this->playerData($player), $login);
            $this->repository->update(Table::PLAYERS_EXTRA, $this->extraPlayerData($login), $login);
        }
    }

    private function deleteFromDB(string $playerID): void
    {
        if (in_array($playerID, $this->getAllLogins())) {
            $this->repository->delete(Table::PLAYERS, 'playerID', $playerID);
            $this->repository->delete(Table::PLAYERS_EXTRA, 'playerID', $playerID);
        }
    }

    public function first(): ?TmContainer
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

    public function getPlayerByLogin(string $login): ?TmContainer
    {
        return $this->container->get($login);
    }

    public function hasPlayer(string $login): bool
    {
        return $this->container->has($login);
    }

    public function getCurrentRanking(): TmContainer
    {
        return $this->client->query('GetCurrentRanking', [0, 100], true);
    }

    public function getDetailedPlayerInfo(string $login): TmContainer
    {
        return $this->client->query('GetDetailedPlayerInfo', [$login], false);
    }

    public function getPlayerInfo(string $login): TmContainer
    {
        return $this->client->query('GetPlayerInfo', [$login, 1]);
    }

    /**
     * Get a list of players from server.
     */
    public function getPlayerList(int $limit = 30, int $start = 0, int $type = 2): array
    {
        return $this->client->query('GetPlayerList', [$limit, $start, $type], false)->get('value');
    }

    private function playerData(TmContainer $player): array
    {
        return [
            'Login'      => $player->get('Login'),
            'Game'       => 'TMF',
            'NickName'   => $player->get('NickName'),
            'playerID'   => $player->get('Login'),
            'Nation'     => $player->get('Nation'),
            'Wins'       => $player->get('LadderStats.NbrMatchWins'),
            'TimePlayed' => time() - $player->get('created'),
            'TeamName'   => $player->get('LadderStats.TeamName'),
            'LastSeen'   => date('Y-m-d H:i:s'),
        ];
    }

    private function extraPlayerData(string $login): array
    {
        return [
            'cps'       => -1,
            'dedicps'   => -1,
            'donations' => 0,
            'style'     => $_ENV['window_style'],
            'panels'    => json_encode([
                'admin'   => $_ENV['admin_panel'],
                'donate'  => $_ENV['donate_panel'],
                'records' => $_ENV['records_panel'],
                'vote'    => $_ENV['vote_panel']
            ]),
            'playerID'  => $login,
        ];
    }
}
