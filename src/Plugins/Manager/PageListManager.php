<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\PlayerService;

class PageListManager
{
    protected TmContainer $player;

    public function __construct(protected PlayerService $playerService)
    {
        $this->playerService->eachPlayer(function (TmContainer $x) {
            $this->player = $x;
            $this->player->set('page.index', 0);
            $this->player->set('page.data', TmContainer::fromArray([]));
            $this->player->set('page.config', TmContainer::fromArray([]));
            $this->player->set('tracklist', TmContainer::fromArray([]));
        });
    }

    public function navigate(int $command): void
    {
        $total = count($this->player['page.data']) - 1;
        $current = $this->player->get('page.index', 0);

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

        $current = max(0, min($current, $total));
        $this->player['page.index'] = $current;
    }

    public function currentPage(): array|string
    {
        $index = $this->player->get('page.index', 0);
        return $this->player->get("page.data.$index", []);
    }

    public function setPages(array $pages, array $config = []): void
    {
        $this->player['page.data']   = TmContainer::fromArray($pages);
        $this->player['page.config'] = TmContainer::fromArray($config);
    }

    public function addPage($page): void
    {
        $pages = $this->player->get('page.data', TmContainer::fromArray([]));
        $pages[] = $page;
        $this->player['page.data'] = $pages;
    }

    public function addTrack(array $track): void
    {
        $tracklist = $this->player->get('tracklist', TmContainer::fromArray([]));
        $tracklist[] = $track;
        $this->player['tracklist'] = $tracklist;
    }

    public function getConfig(): array
    {
        return $this->player->get('page.config', []);
    }
}
