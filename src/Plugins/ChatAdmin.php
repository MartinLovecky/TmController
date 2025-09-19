<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\Manager\PluginManager;

class ChatAdmin
{
    private ?RaspJukebox $raspJukebox = null;
    private array $pmBuff = [];
    private int $pmLen = 30;
    private int $lnLen = 40;
    private bool $autoScorePanel = true;
    private bool $roundsFinishPanel = true;

    public function setRegistry(PluginManager $registry): void
    {
        $this->raspJukebox = $registry->raspJukebox;
    }

    public function handleChatCommand(TmContainer $player): void
    {
        $login = $player->get('Login');

        // inspired by Trackman //admin commands TODO:: isAnyAdmin need be changed
        if (str_starts_with($player->get('command.name'), '/') && !Aseco::isAnyAdmin($login)) {
            return;
        }

        match ($player->get('command.param')) {
            'kick' => null,
            'ban' => null,
            'restart' => null,
            'jukebox' => null,
            'help' => null,
            'helpall' => null,
            default => null
        };
    }

    public function warn(TmContainer $admin, $command, $logtitle, $chattitle)
    {
        $login = $admin->get('Login');
    }

    public function admin()
    {
    }
}
