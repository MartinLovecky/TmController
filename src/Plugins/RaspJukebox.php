<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\App\Command;

class RaspJukebox
{
    private array $commands = [];
    private array $buffer = [];
    private int $bufferSize = 0;

    public function __construct()
    {
        $this->commands = [
            ['list', [$this, 'list'], 'Lists tracks currently on the server (see: /list help)'],
            ['jukebox', [$this, 'jukebox'], 'Sets track to be played next (see: /jukebox help)'],
            ['autojuke', [$this, 'autojuke'], 'Jukeboxes track from /list (see: /autojuke help)'],
            ['add', [$this, 'add'], 'Adds a track directly from TMX (<ID> {sec})'],
            ['y', [$this, 'y'], 'Votes Yes for a TMX track or chat-based vote'],
            ['history', [$this, 'history'], 'Shows the 10 most recently played tracks'],
            ['xlist', [$this, 'xlist'], 'Lists tracks on TMX (see: /xlist help)']
        ];
        Command::register($this->commands, 'RaspJukebox');
    }

    public function onSync(): void
    {
        $filePath = Aseco::path(3)
            . 'GameData'
            . DIRECTORY_SEPARATOR
            . 'Tracks'
            . DIRECTORY_SEPARATOR
            . 'trackhist.txt';

        if (!is_readable($filePath)) {
            throw new \RuntimeException("Failed to open track history file: {$filePath}");
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $this->buffer = array_slice($lines, -$this->bufferSize);
        array_pop($this->buffer);
    }
}
