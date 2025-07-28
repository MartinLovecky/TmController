<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Command;

class ChatDedimania
{
    private array $commands = [];

    public function __construct()
    {
        $this->commands = [
            ['helpdedi', [$this, 'helpdedi'], 'Displays info about the Dedimania records system'],
            ['dedihelp', [$this, 'dedihelp'], 'Displays info about the Dedimania records system'],
            ['dedirecs', [$this, 'dedirecs'], 'Displays all Dedimania records on current track'],
            ['dedinew', [$this, 'dedinew'], 'Shows newly driven Dedimania records'],
            ['dedilive', [$this, 'dedilive'], 'Shows Dedimania records of online players'],
            ['dedipb', [$this, 'dedipb'], 'Shows your Dedimania personal best on current track'],
            ['dedifirst', [$this, 'dedifirst'], 'Shows first Dedimania record on current track'],
            ['dedilast',  [$this, 'dedilast'], 'Shows last Dedimania record on current track'],
            ['dedinext',  [$this, 'dedinext'], 'Shows next better Dedimania record to beat'],
            ['dedidiff',  [$this, 'dedidiff'], 'Shows your difference to first Dedimania record'],
            ['dedirange',  [$this, 'dedirange'], 'Shows difference first to last Dedimania record'],
            ['dedicps',  [$this, 'dedicps'], 'Sets Dedimania record checkspoints tracking'],
            ['dedistats',  [$this, 'dedistats'], 'Displays Dedimania track statistics'],
            ['dedicptms',  [$this, 'dedicptms'], 'Displays all Dedimania records\' checkpoint times'],
            ['dedisectms',  [$this, 'dedisectms'], 'Displays all Dedimania records\' sector times']
        ];
        Command::register($this->commands, 'ChatDedimania');
    }
}
