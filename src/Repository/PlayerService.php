<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Plugins\{Panels, Styles};
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
        protected RepositoryManager $repository,
        protected Styles $styles,
    ) {
        // Initialize with first connected player as master admin
        if ($firstLogin = $this->getFirstPlayerLogin()) {
            $this->addPlayer($firstLogin, true);
        }
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

        $info = $this->getDetailedPlayerInfo($login);
        $info
            ->set('Nation', Aseco::mapCountry($info['Path']))
            ->set('Donation', [100, 200, 500, 1000, 2000])
            ->set('panels.admin', $this->panels->adminPanel)
            ->set('panels.donate', $this->panels->donatePanel)
            ->set('panels.records', $this->panels->recordsPanel)
            ->set('panels.vote', $this->panels->votePanel)
            ->set('style', $this->styles->style)
            ->set('created', time());

        if ($isMasterAdmin) {
            $info->set('isMasterAdmin', true);
        }

        $this->container->set($login, $info);

        $this->numSpecs = $this->container->get("$login.IsSpectator") === true
            ? $this->numSpecs + 1
            : $this->numSpecs;

        $this->numPlayers = $this->container->count();

        // Save to DB
        $this->repository->insert(Table::PLAYERS, $this->playerData($info), $login);
        $this->repository->insert(Table::PLAYERS_EXTRA, $this->extraPlayerData($login), $login);
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
     * @param Container<string, Container<string, mixed>> $players
     */
    public function update(Container $player): void
    {
        foreach ($player->getIterator() as $login => $player) {
            $this->repository->update(Table::PLAYERS, $this->playerData($player), $login);
            $this->repository->update(Table::PLAYERS_EXTRA, $this->extraPlayerData($login), $login);
        }
    }

    public function delete(string $playerID): void
    {
        if (in_array($playerID, $this->getAllLogins())) {
            $this->container->delete($playerID);
            $this->repository->delete(Table::PLAYERS, 'playerID', $playerID);
            $this->repository->delete(Table::PLAYERS_EXTRA, 'playerID', $playerID);
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

    public function getDetailedPlayerInfo(string $login): Container
    {
        return $this->client->query('GetDetailedPlayerInfo', [$login], false);
    }

    /**
     * Get a list of players from server.
     */
    private function getPlayerList(int $limit = 300, int $start = 0, int $type = 2): array
    {
        return $this->client->query('GetPlayerList', [$limit, $start, $type], false)->get('value');
    }

    /**
     * Get first player's login if exists.
     */
    private function getFirstPlayerLogin(): ?string
    {
        $list = $this->getPlayerList(1); // fetch just 1 player
        if (empty($list)) {
            return null;
        }
        return $list[0]->get('Login');
    }

    private function playerData(Container $player): array
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
