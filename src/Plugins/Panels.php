<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\App\Service\WidgetBuilder;

class Panels
{
    public ?string $adminPanel = null;
    public ?string $donatePanel = null;
    public ?string $recordsPanel = null;
    public ?string $votePanel = null;

    public function __construct(protected WidgetBuilder $widgetBuilder)
    {
        $folder = 'panels' . DIRECTORY_SEPARATOR;
        //Admin panel
        $adminFile = "{$folder}{$_ENV['admin_panel']}";
        Aseco::console('Default admin panel [{1}]', $_ENV['admin_panel']);
        $this->adminPanel = $this->widgetBuilder->render($adminFile);
        //Donate panel
        $donateFile = "{$folder}{$_ENV['donate_panel']}";
        Aseco::console('Default donnate panel [{1}]', $_ENV['donate_panel']);
        $this->donatePanel = $this->widgetBuilder->render($donateFile);
        //Records panel
        $recordsFile = "{$folder}{$_ENV['records_panel']}";
        Aseco::console('Default records panel [{1}]', $_ENV['records_panel']);
        $this->recordsPanel = $this->widgetBuilder->render($recordsFile);
        //Vote panel
        $voteFile = "{$folder}{$_ENV['vote_panel']}";
        Aseco::console('Default vote panel [{1}]', $_ENV['vote_panel']);
        $this->votePanel = $this->widgetBuilder->render($voteFile);
    }
}
