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
    /** @var object[] $plugins*/
    private array $plugins = [];
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
        foreach (get_object_vars($this) as $propertyName => $pluginInstance) {
            if ($propertyName === 'plugins') {
                continue;
            }
            if (is_object($pluginInstance)) {
                $this->plugins[$propertyName] = $pluginInstance;
            }
        }
    }

    /**
     * Calls a method on a specific plugin if it exists.
     *
     * @param string $pluginName Plugin property name in camelCase
     * @param string $functionName camelCase method name to call
     * @param mixed ...$args Arguments to pass to the method
     *
     * @return mixed|null Returns the method result or null
     */
    public function callPluginFunction(string $pluginName, string $functionName, ...$args): mixed
    {
        $plugin = $this->getPlugin($pluginName);
        if ($plugin !== null && method_exists($plugin, $functionName)) {
            return $plugin->$functionName(...$args);
        }

        return null;
    }

    /**
     * Calls a method on all plugins that implement it.
     *
     * @param string $functionName The method name to call
     * @param mixed ...$args Arguments to pass to the method
     *
     * @return void
     */
    public function callFunctions(string $functionName, ...$args): void
    {
        foreach ($this->plugins as $plugin) {
            if (method_exists($plugin, $functionName)) {
                $plugin->$functionName(...$args);
            }
        }
    }

    /**
     * Retrieves a plugin instance by its camelCase property name.
     *
     * @param string $pluginName Plugin property name in camelCase
     * @return object|null The plugin instance or null if not found
     */
    private function getPlugin(string $pluginName): ?object
    {
        return $this->plugins[$pluginName] ?? null;
    }
}
