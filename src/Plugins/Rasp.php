<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\App\Service\Sender;
use Yuhzel\TmController\App\Service\Server;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Rasp\RaspState;

class Rasp
{
    public function __construct(
        private RaspState $raspState,
        private Sender $sender,
    ) {
    }

    public function onSync(): void
    {
        $folder = Server::$trackDir . $this->raspState->tmxdir;
        if (!file_exists($folder)) {
            mkdir($folder);
        }
        if ($this->raspState->feature_tmxadd) {
            $tempFolder = Server::$trackDir . $this->raspState->tmxtmpdir;
            if (!file_exists($tempFolder)) {
                mkdir($tempFolder);
                $this->raspState->feature_tmxadd = false;
            }
        }
    }

    public function onPlayerConnect(TmContainer $player): void
    {
        if ($this->raspState->feature_ranks) {
            $login = $player->get('Login');
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('rank_none', 'rasp'),
                formatArgs: [$this->raspState->minrank]
            );
        }
    }
}
