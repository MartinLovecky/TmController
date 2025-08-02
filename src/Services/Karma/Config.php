<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services\Karma;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;

class Config
{
    protected static ?Container $cached = null;

    public static function get(): Container
    {
        if (!self::$cached) {
            self::$cached = Container::fromJsonFile(Aseco::jsonFolderPath() . 'maniaKarma.json');
        }

        return self::$cached;
    }
}
