<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\App\Command;

class Panels
{
    private array $commands = [];
    public ?string $adminPanel = null;
    public ?string $donatePanel = null;
    public ?string $recordsPanel = null;
    public ?string $votePanel = null;


    public function __construct()
    {
        $this->commands = [
            ['donpanel',  [$this, 'donpanel'],  'Selects donate panel (see: /donpanel help)'],
            ['recpanel',  [$this, 'recpanel'],  'Selects records panel (see: /recpanel help)'],
            ['votepanel', [$this, 'votepanel'], 'Selects vote panel (see: /votepanel help)']
        ];
        Command::register($this->commands, 'Panels');
        //Admin panel
        $adminFile = Aseco::path() . "public/xml/panels/{$_ENV['admin_panel']}.xml";
        Aseco::console('Default admin panel [{1}]', $adminFile);
        $this->adminPanel = Aseco::safeFileGetContents($adminFile);
        //Donate panel
        $donateFile = Aseco::path() . "public/xml/panels/{$_ENV['donate_panel']}.xml";
        Aseco::console('Default donnate panel [{1}]', $donateFile);
        $this->donatePanel = Aseco::safeFileGetContents($donateFile);
        //Records panel
        $recordsFile = Aseco::path() . "public/xml/panels/{$_ENV['records_panel']}.xml";
        Aseco::console('Default records panel [{1}]', $recordsFile);
        $this->recordsPanel = Aseco::safeFileGetContents($recordsFile);
        //Vote panel
        $voteFile = Aseco::path() . "public/xml/panels/{$_ENV['vote_panel']}.xml";
        Aseco::console('Default vote panel [{1}]', $voteFile);
        $this->votePanel = Aseco::safeFileGetContents($voteFile);
    }
}
