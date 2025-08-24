<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Aseco, Server};
use Yuhzel\TmController\Core\TmContainer;

class Styles
{
    public TmContainer $style;

    public function __construct()
    {
        $styleFile =
            Server::$jsonDir
            . 'styles'
            . DIRECTORY_SEPARATOR
            . $_ENV['window_style']
            . '.json';
        Aseco::console('Load default style [{1}]', $styleFile);
        $this->style = TmContainer::fromJsonFile($styleFile);
    }
}
