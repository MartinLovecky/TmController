<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App;

use Yuhzel\TmController\App\Aseco;

class Command
{
    /** @var array<mixed> $all */
    public static array $all = [];

    /**
     * Register plugin commands
     *
     * @param array<mixed>
     * @param string $plugin
     * @param bool $isAdmin
     * @return void
     */
    public static function register(
        array $commands,
        string $plugin,
        bool $isAdmin = false
    ): void {
        foreach ($commands as $command) {
            [$name, $callback, $description] = $command;

            if (!is_string($name)) {
                Aseco::console("Invaldi name for command {$name} in {$plugin}");
                continue;
            }

            if (is_array($callback) && count($callback) === 2) {
                [$class, $method] = $callback;

                if (is_object($class) && method_exists($class, $method)) {
                    self::$all[$name] = [
                        'name' => $name,
                        'callback' => $callback,
                        'help' => $description,
                        'isAdmin' => $isAdmin,
                        'plugin' => $plugin
                    ];
                }
            } else {
                Aseco::console("Invalid callback structure for command: {$name} in {$plugin}");
            }
        }
    }

    /**
     * @param string $plugin
     * @return array<mixed>
     */
    public static function getCommands(string $plugin): array
    {
        return array_filter(self::$all, function ($command) use ($plugin) {
            return is_array($command)
                && isset($command['plugin'])
                && $command['plugin'] === $plugin;
        });
    }

    /**
     * @param null|string $plugin
     * @return array<mixed>
     */
    public static function getHelp(?string $plugin = null): array
    {
        $filteredCmd = $plugin ? self::getCommands($plugin) : self::$all;
        $helpText = array_map(function ($cmd) {
            return is_array($cmd) && isset($cmd['help']) ? $cmd['help'] : '';
        }, $filteredCmd);

        $helpText['ptr'] = 1;
        $helpText['header'] = "Currently supported commands";
        $helpText['width'] = [1.3, 0.3, 1.0];
        $helpText['icon'] = ['Icons63x64_1', 'TrackInfo', -0.01];

        return $helpText;
    }
}
