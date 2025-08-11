<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\Container;

class ChatAdmin
{
    public function warn(Container $admin, $command, $logtitle, $chattitle)
    {
        $login = $admin->get('Login');
    }
}
