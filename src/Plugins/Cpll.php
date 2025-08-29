<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\App\Service\Sender;
use Yuhzel\TmController\Core\TmContainer;

class Cpll
{
    public function __construct(private Sender $sender)
    {
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        $this->sender->sendChatMessageToLogin(
            login: $player->get('Login'),
            message: Aseco::getChatMessage('cpll_info', 'rasp'),
            formatMode: Sender::FORMAT_COLORS
        );
    }
}
