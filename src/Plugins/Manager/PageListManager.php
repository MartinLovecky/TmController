<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\PlayerService;

class PageListManager
{
    protected array $players = [];

    public function __construct(protected PlayerService $playerService)
    {
        $this->playerService->eachPlayer(function (TmContainer $x) {
            $login = $x->get('Login');
            $x->set('page.index', 0);
            $x->set('page.data', TmContainer::fromArray([]));
            $x->set('page.config', TmContainer::fromArray([]));
            $x->set('tracklist', TmContainer::fromArray([]));

            $this->players[$login] = $x;
        });
    }

    public function navigate(string $login, int $command): void
    {
        $player  = $this->player($login);
        $total   = count($player['page.data']) - 1;
        $current = $player->get('page.index', 0);

        switch ($command) {
            case -4:
                $current = 0;
                break;
            case -3:
                $current -= 5;
                break;
            case -2:
                $current -= 1;
                break;
            case 1:
                break;
            case 2:
                $current += 1;
                break;
            case 3:
                $current += 5;
                break;
            case 4:
                $current = $total;
                break;
        }

        $player['page.index'] = max(0, min($current, $total));
    }

    public function currentPage(string $login): array|string
    {
        $player = $this->player($login);
        $index = $player->get('page.index', 0);
        return $player->get("page.data.$index", []);
    }

    public function goLastPage(string $login): void
    {
        $player = $this->player($login);
        $total   = count($player->get('page.data', [])) - 1;
        $player['page.index'] = max(0, $total);
    }

    public function setPages(string $login, array $pages, array $config = []): void
    {
        $player = $this->player($login);
        $player['page.data']   = TmContainer::fromArray($pages);
        $player['page.config'] = TmContainer::fromArray($config);
    }

    public function addPage(string $login, $page): void
    {
        $player = $this->player($login);
        $pages = $player->get('page.data', TmContainer::fromArray([]));
        $pages[] = $page;
        $player['page.data'] = $pages;
    }

    public function addTrack(string $login, array $track): void
    {
        $player = $this->player($login);
        $tracklist = $player->get('tracklist', TmContainer::fromArray([]));
        $tracklist[] = $track;
        $player['tracklist'] = $tracklist;
    }

    public function getConfig(string $login): array
    {
        $player = $this->player($login);
        return $player->get('page.config', []);
    }

    protected function player(string $login): TmContainer
    {
        return $this->players[$login];
    }
}
