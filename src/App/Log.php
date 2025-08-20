<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;

class Log
{
    /** @var array<string, Logger> */
    private static array $loggers = [];

    private static function getLogger(string $channel = 'trackmania'): Logger
    {
        if (!isset(self::$loggers[$channel])) {
            $logger = new Logger($channel);

            $handler = new StreamHandler(
                Aseco::path() . "logs" . DIRECTORY_SEPARATOR . "{$channel}.log",
                Level::Debug
            );

            $formatter = new JsonFormatter();
            $formatter->includeStacktraces(true); // optional
            $formatter->setJsonPrettyPrint(true); // pretty print JSON

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            self::$loggers[$channel] = $logger;
        }

        return self::$loggers[$channel];
    }

    public static function info(string $message, array $context = [], string $channel = 'trackmania'): void
    {
        self::getLogger($channel)->info($message, $context);
    }

    public static function debug(string $message, array $context = [], string $channel = 'trackmania'): void
    {
        self::getLogger($channel)->debug($message, $context);
    }

    public static function warning(string $message, array $context = [], string $channel = 'trackmania'): void
    {
        self::getLogger($channel)->warning($message, $context);
    }

    public static function error(string $message, array $context = [], string $channel = 'trackmania'): void
    {
        self::getLogger($channel)->error($message, $context);
    }
}
