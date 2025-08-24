<?php

/**
 * Trackmania Server Controller
 * php version 8.3.0 or higher
 *
 * @category Controller
 * @package  Trackmania
 * @author   yuhzel <yuhzel@gmail.com>
 * @license  MIT LICENCE
 * @version  GIT: 5ba0430ef3c51a38ac5e9f86b0cac60dc5bddcd9
 * @link     https://example.com
 */

declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.3.0', '<')) {
    throw new \RuntimeException('This script requires PHP 8.3.0 or higher.');
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
$publicPath = __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR;

$container = new \League\Container\Container();
$container->add('config.paths', [
    'root'      => __DIR__,
    'game'      => dirname(__DIR__, 1),
    'public'    => $publicPath,
    'json'      => $publicPath . 'json' . DIRECTORY_SEPARATOR,
    'templates' => $publicPath . 'templates' . DIRECTORY_SEPARATOR,
    'sql'       => $publicPath . 'sql' . DIRECTORY_SEPARATOR,
    'logs'      => __DIR__ . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR
]);
$container->delegate(new \League\Container\ReflectionContainer(true));

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$dotenv->required([
    'admin_login',
    'admin_password',
    'server_login',
    'dedi_username',
    'dedi_code',
    'dedi_nation'
])->notEmpty();

Yuhzel\TmController\Services\Server::init($container);

$fluent = $container->get(\Yuhzel\TmController\Database\Fluent::class);
$structure = $container->get(\Yuhzel\TmController\Database\Structure::class);

foreach (Yuhzel\TmController\Database\Table::cases() as $table) {
    $fluent->executeFile($table);
    $structure->validate($table);
}

$controller = $container->get(\Yuhzel\TmController\App\Controller\TmController::class);
$controller->run();
