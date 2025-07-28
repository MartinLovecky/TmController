<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\Client;

class Cpll
{
    public function __construct(protected Client $client)
    {
    }

    public function onPlayerConnect(Container $player): void
    {
        $message = Aseco::getChatMessage('cpll_info', 'rasp');

        $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message),
                $player->get('Login')
        ]);
    }
}
