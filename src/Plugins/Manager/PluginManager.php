<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\ChallMapFetcher;
use Yuhzel\TmController\Plugins\{
    CpLiveAdvanced,
    Dedimania,
    FufiMenu,
    ChatAdmin,
    ChatCmd,
    ChatDedimania,
    Checkpoints,
    Cpll,
    ManiaKarma,
    ManiaLinks,
    Panels,
    Rasp,
    RaspJukebox,
    RaspVotes,
    Styles,
    Tmxv
};
use Yuhzel\TmController\Repository\ChallengeService;

class PluginManager
{
    public function __construct(
        private CpLiveAdvanced $cpLiveAdvanced,
        private Cpll $cpll,
        private Dedimania $dedimania,
        private FufiMenu $fufiMenu,
        private ChatAdmin $chatAdmin,
        private ChatCmd $chatCmd,
        private ChatDedimania $chatDedimania,
        private Checkpoints $checkpoints,
        private ManiaKarma $maniaKarma,
        private ManiaLinks $maniaLinks,
        private Panels $panels,
        private Rasp $rasp,
        private RaspJukebox $raspJukebox,
        private RaspVotes $raspVotes,
        private Styles $styles,
        private Tmxv $tmxv
    ) {
    }

    public function getPlugin(string $pluginName): ?object
    {
        if (property_exists($this, lcfirst($pluginName))) {
            $plugin = lcfirst($pluginName);
            if (is_object($this->{$plugin})) {
                return $this->{$plugin};
            }
        }

        Aseco::console("Plugin {$pluginName} not found in PluginManager constructor.");
        return null;
    }

    public function pluginFunctionExists(string $functionName): bool
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, $functionName)) {
                return true;
            }
        }

        return false;
    }

    public function callPluginFunction(string $functionName, ...$args): mixed
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, $functionName)) {
                return call_user_func_array([$plugin, $functionName], $args);
            }
        }

        return null;
    }

    public function onSync(): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onSync')) {
                $plugin->onSync();
            }
        }
    }

    public function onPlayerConnect(Container $player): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onPlayerConnect')) {
                $plugin->onPlayerConnect($player);
            }
        }
    }

    public function onPlayerDisconnect(Container $player): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onPlayerDisconnect')) {
                $plugin->onPlayerDisconnect($player);
            }
        }
    }

    public function onMainLoop(): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onMainLoop')) {
                $plugin->onMainLoop();
            }
        }
    }

    public function onEverySecond(): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onEverySecond')) {
                $plugin->onEverySecond();
            }
        }
    }

    public function onRestartChallenge(ChallMapFetcher $gbx)
    {
    }

    public function onNewChallenge(ChallengeService $challenge)
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onNewChallenge')) {
                $plugin->onNewChallenge($challenge);
            }
        }
    }

    public function onNewChallenge2(ChallMapFetcher $gbx)
    {
    }

    public function onChat($call): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onChat')) {
                $plugin->onChat($call);
            }
        }
    }

    public function onBeginRound(): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onBeginRound')) {
                $plugin->onBeginRound();
            }
        }
    }

    public function onEndRound(): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onEndRound')) {
                $plugin->onEndRound();
            }
        }
    }

    public function onStatusChangeTo(): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, 'onStatusChangeTo')) {
                $plugin->onStatusChangeTo();
            }
        }
    }
}
