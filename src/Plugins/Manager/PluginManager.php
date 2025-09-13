<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Manager;

use Yuhzel\TmController\Plugins\{
    CpLiveAdvanced,
    Dedimania,
    FufiMenu,
    ChatAdmin,
    ChatCmd,
    ChatDedimania,
    Checkpoints,
    Cpll,
    Donate,
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
        public CpLiveAdvanced $cpLiveAdvanced,
        public Cpll $cpll,
        public Dedimania $dedimania,
        public Donate $donate,
        public FufiMenu $fufiMenu,
        public ChatAdmin $chatAdmin,
        public ChatCmd $chatCmd,
        public ChatDedimania $chatDedimania,
        public Checkpoints $checkpoints,
        public ManiaKarma $maniaKarma,
        public ManiaLinks $maniaLinks,
        public Panels $panels,
        public RaspVotes $raspVotes,
        public RaspJukebox $raspJukebox,
        public Styles $styles,
        public Tmxv $tmxv,
        public Track $track
    ) {
        // if you need read docs/PluginManager.txt
        $this->callFunctions('setRegistry', $this);
    }

    /**
     * Calls a method on a specific plugin by class name.
     *
     * @param string|object $pluginClass Fully qualified class name of the plugin
     * @param string $functionName Method name to call
     * @param mixed ...$args Arguments to pass to the method
     * @return mixed|null Returns method result or null if plugin/method not found
     */
    public function callPluginFunction(string|object $pluginClass, string $functionName, ...$args): mixed
    {
        $plugin = null;

        // Find plugin instance by class
        foreach (get_object_vars($this) as $property) {
            if ($property instanceof $pluginClass) {
                $plugin = $property;
                break;
            }
        }

        if (!$plugin || !method_exists($plugin, $functionName)) {
            return null;
        }

        return $plugin->$functionName(...$args);
    }

    /**
     * Calls a method on all plugins that implement it.
     *
     * @param string $functionName Method name to call
     * @param mixed ...$args Arguments to pass
     */
    public function callFunctions(string $functionName, ...$args): void
    {
        foreach (get_object_vars($this) as $plugin) {
            if (!is_object($plugin)) {
                continue;
            }
            if (method_exists($plugin, $functionName)) {
                $plugin->$functionName(...$args);
            }
        }
    }
}
