<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Repository\PlayerService;

class PageListManager
{
    protected array $players = [];

    public function __construct(private PlayerService $playerService)
    {
        $this->playerService->eachPlayer(function (TmContainer $x) {
            $login = $x->get('Login');
            $this->initDefaults($x);
            $this->players[$login] = $x;
        });
    }

    public function navigate(string $login, int $command): void
    {
        $player  = $this->player($login);
        $pages   = $player->get('page.data', []);
        $total   = max(0, count($pages) - 1);
        $current = (int) $player->get('page.index', 0);

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
            case 1:  /* stays on current */
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

    public function currentPage(string $login): array
    {
        $player = $this->player($login);
        $index  = (int) $player->get('page.index', 0);
        $pages  = $player->get('page.data', []);
        return $pages[$index] ?? [];
    }

    public function goLastPage(string $login): void
    {
        $player = $this->player($login);
        $pages  = $player->get('page.data', []);
        $total  = max(0, count($pages) - 1);
        $player['page.index'] = $total;
    }

    public function setPages(string $login, array $pages, array $config = []): void
    {
        $player = $this->player($login);
        $player['page.data']   = $pages;
        $player['page.config'] = $config + $this->defaultConfig();
        $player['page.index']  = 0;
    }

    public function addPage(string $login, $page): void
    {
        $player = $this->player($login);
        $pages  = $player->get('page.data', []);
        $pages[] = $page;
        $player['page.data'] = $pages;
    }

    public function addTrack(string $login, array $track): void
    {
        $player    = $this->player($login);
        $tracklist = $player->get('tracklist', []);
        $tracklist[] = $track;
        $player['tracklist'] = $tracklist;
    }

    public function getConfig(string $login): array
    {
        $player = $this->player($login);
        return $player->get('page.config', $this->defaultConfig());
    }

    protected function player(string $login): TmContainer
    {
        if (!isset($this->players[$login])) {
            // lazy init if missing
            $x = new TmContainer();
            $x->set('Login', $login);
            $this->initDefaults($x);
            $this->players[$login] = $x;
        }
        return $this->players[$login];
    }

    protected function initDefaults(TmContainer $x): void
    {
        $x->set('page.index', 0);
        $x->set('page.data', []);
        $x->set('page.config', $this->defaultConfig());
        $x->set('tracklist', []);
        $x->set('style', TmContainer::fromArray([
            'header.textsize' => 1.0,
            'body.textsize'   => 0.8,
            'window.style'    => 'Bgs1InRace',
            'window.substyle' => 'BgList',
            'header.style'    => 'Bgs1InRace',
            'header.substyle' => 'BgTitle3',
            'header.textstyle' => 'TextCardSmall',
            'body.style'      => 'Bgs1InRace',
            'body.substyle'   => 'BgWindow2',
            'body.textstyle'  => 'TextCardInfoSmall',
            'button.style'    => 'Icons64x64_1',
            'button.substyle' => 'ArrowNext',
        ]));
    }

    protected function defaultConfig(): array
    {
        return [
            'header' => '',
            'widths' => [1.0],
            'icon'   => '',
        ];
    }
}
