<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Services\RaspState;
use Yuhzel\TmController\Services\Server;

class Rasp
{
    public function __construct(protected RaspState $raspState)
    {
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
}
