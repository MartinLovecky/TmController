<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;

class ChatAdmin
{
    public function warn(TmContainer $admin, $command, $logtitle, $chattitle)
    {
        $login = $admin->get('Login');
    }
}
