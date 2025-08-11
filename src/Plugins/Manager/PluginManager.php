<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

use Yuhzel\TmController\App\Aseco;
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
    RaspJukebox,
    RaspVotes,
    Styles,
    Tmxv,
    Track
};

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
        private RaspJukebox $raspJukebox,
        private RaspVotes $raspVotes,
        private Styles $styles,
        private Tmxv $tmxv,
        private Track $track
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

    public function callFunctions(string $functionName, ...$args): mixed
    {
        foreach (get_object_vars($this) as $plugin) {
            if (is_object($plugin) && method_exists($plugin, $functionName)) {
                return call_user_func_array([$plugin, $functionName], $args);
            }
        }

        return null;
    }

    public function callPluginFunction(string $pluginName, string $functionName, ...$args): mixed
    {
        if (method_exists($this->getPlugin($pluginName), $functionName)) {
            return call_user_func_array([$this->getPlugin($pluginName), $functionName], $args);
        }

        return null;
    }
}
