<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\ManiaLinks;
use Yuhzel\TmController\Repository\PlayerService;
use Yuhzel\TmController\App\Service\{Aseco, Server};
use Yuhzel\TmController\Plugins\Manager\PluginManager;
use Yuhzel\TmController\Plugins\Manager\PageListManager;

/**
 * Currenty we support just one style if you want more logic need be changed
 * @author Yuha <yuhzel@email.com>
 */
class Styles
{
    public TmContainer $style;
    private ?ManiaLinks $maniaLinks = null;

    public function __construct(
        private PageListManager $pageListManager,
        private PlayerService $playerService,
    ) {
        $styleFile =
            Server::$jsonDir
            . 'styles'
            . DIRECTORY_SEPARATOR
            . $_ENV['window_style']
            . '.json';
        Aseco::console('Load default style [{1}]', $styleFile);
        $this->style = TmContainer::fromJsonFile($styleFile);
    }

    public function setRegistry(PluginManager $registry): void
    {
        $this->maniaLinks = $registry->maniaLinks;
    }

    public function handleChatCommand(TmContainer $player): void
    {
        if ($player->get('command.name') !== 'style') {
            return;
        }

        match ($player->get('command.param')) {
            'help' => $this->showHelp($player),
            'list' => $this->showStyleList($player),
            default => null
        };
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        $player->set('style', $this->style);
    }

    public function onPlayerLink(TmContainer $manialink): void
    {
        $message = $manialink->get('message');

        if ($message >= 49 && $message <= 100) {
            $login = $manialink->get('login');
            $player = $this->playerService->getPlayerByLogin($login);

            Aseco::console("{$login} clicked command /style DarkBlur");
            $player->setMultiple([
                    'command.name' => 'style',
                    'command.param' => 'DarkBlur',
                    'command.arg' => ''
            ]);
            $this->handleChatCommand($player);

            Aseco::console("{$login} clicked command /style list");
            $player->setMultiple([
                    'command.name' => 'style',
                    'command.param' => 'list',
                    'command.arg' => ''
                ]);
            $this->handleChatCommand($player);
        }
    }

    private function showHelp(TmContainer $player): void
    {
        $header = '$f00/style$g will change the window style:';
        $helpItems = [
            ['command' => '$f00help',    'description' => 'Displays this help information'],
            ['command' => '$f00list',    'description' => 'Displays available styles'],
            //['command' => '$f00default', 'description' => 'Resets style to server default'],
            //['command' => '$f00off',     'description' => 'Disables TMF window style'],
            //['command' => '$f00xxx',     'description' => 'Selects window style xxx'],
        ];

        $this->maniaLinks->displayManialink(
            $player->get('Login'),
            $header,
            ['Icons64x64_1', 'TrackInfo', -0.01],
            $helpItems,
            [0.8, 0.05, 0.15, 0.6],
            'OK'
        );
    }

    private function showStyleList(TmContainer $player): void
    {
        $login = $player->get('Login');
        $head  = 'Currently available window styles:';
        $pages = [
            [
                [
                    '01.',
                    ['$f00' . $_ENV['window_style'], 49]
                ]
            ]
        ];

        /** this would you just display 'avaible styles'
         * to support multiple styles - you need implemet changing them
         *   $styles = [$_ENV['window_style']]; //list of all styles
         *   $pages = [];
         *   $currentPage = [];
         *   $sid = 1;
         *
         * foreach ($styles as $style) {
         *      $currentPage[] = [
         *          str_pad((string)$sid, 2, '0', STR_PAD_LEFT) . '.',
         *          ['$f00' . $style, $sid + 48]
         *      ];
         *   $sid++;
         *  }
         */

        $this->pageListManager->setPages($login, $pages, [
            'index'  => 1,
            'header' => $head,
            'widths' => [0.8, 0.1, 0.7],
            'icon'   => ['Icons64x64_1', 'Windowed']
        ]);

        $manialink = TmContainer::fromArray([
            'message' => 1,
            'login'   => $login
        ]);

        $this->maniaLinks->eventManiaLink($manialink);
    }
}
