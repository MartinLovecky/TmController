<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Infrastructure\Gbx\Client;

class DiscordBot
{
    public function __construct(protected Client $client)
    {
    }

    public function onMainLoop()
    {
        //Connect to discord ?

        $this->getMessages();

        // send back to discord
    }

    private function getMessages()
    {
        $lines = $this->client->query('GetChatLines', [50, 0]);
        if (!$lines->isEmpty()) {
            foreach ($lines->getIterator() as $msg) {
                if (!strstr($msg, '!D')) {
                }
            }
        }
    }
}
