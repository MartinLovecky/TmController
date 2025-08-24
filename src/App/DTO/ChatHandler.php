<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\DTO;

use League\Container\Container;
use Yuhzel\TmController\App\Service\Aseco;

class ChatHandler
{
    public function handle(Container $call): void
    {
        $player = $call->player ?? null;
        if (!$player) {
            return;
        }
        dd($call);
        Aseco::consoleText("[Chat] {$player->get('NickName')}: " . $call);
    }
}
