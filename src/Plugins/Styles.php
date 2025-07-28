<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;

class Styles
{
    public Container $style;

    public function __construct()
    {
        $styleFile = Aseco::jsonFolderPath()
            . 'styles'
            . DIRECTORY_SEPARATOR
            . $_ENV['window_style']
            . '.json';
        Aseco::console('Load default style [{1}]', $styleFile);
        $this->style = Container::fromJsonFile($styleFile);
    }
}
