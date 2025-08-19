<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;

class Server
{
    public static int $id = 0;
    public static string $ip = '';
    public static int $port = 0;
    public static string $login = '';
    public static string $pass = '';
    public static string $serverLogin = '';
    public static string $game = 'TMF';
    public static string $gameState = 'race';
    public static string $version = '2.11.26';
    public static string $build = '';
    public static string $nickName = '';
    public static string $gameDir = '';
    public static string $trackDir = '';
    public static float $timeout = 10.0;
    public static int $startTime = 0;
    public static int $rights = 0;
    public static float $ladderMin = 0.0;
    public static float $ladderMax = 0.0;
    public static string $packmask = '';
    public static string $name = '';
    public static string $comment = '';
    public static int $maxPlayers = 0;
    public static int $maxSpectators = 0;
    public static int $ladderMode = 0;
    public static int $vehicleNetQuality = 0;
    public static int $callVoteTimeout = 0;
    public static float $callVoteRatio = 0.0;
    public static string $gamePath = '';
    public static string $zone = '';
    public static bool $private = false;
    public static array $muteList = [];

    public function __construct()
    {
        self::$startTime = time();
        self::$gamePath = Aseco::path(3);
        self::$gameDir = self::$gamePath . 'GameData' . DIRECTORY_SEPARATOR;
        self::$trackDir = self::$gameDir . 'Tracks' . DIRECTORY_SEPARATOR;
        self::$login = $_ENV['admin_login'];
        self::$pass = $_ENV['admin_password'];
        self::$ip = $_ENV['server_ip'];
        self::$port = (int)$_ENV['server_port'];
        self::$timeout = (float)$_ENV['server_timeout'];
    }

    public function setServerInfo(Container $admin): void
    {
        self::$id = $admin->get('PlayerId');
        self::$nickName = $admin->get('NickName');
        self::$zone = substr($admin->get('Path'), 6);
        self::$rights = $admin->get('OnlineRights');
    }

    public function setVersion(Container $version): void
    {
        self::$version = $version->get('Version');
        self::$build = $version->get('Build');
    }

    public function setLadder(Container $ladder): void
    {
        self::$ladderMin = $ladder->get('LadderServerLimitMin');
        self::$ladderMax = $ladder->get('LadderServerLimitMax');
    }

    public function setPackMask(string $mask): void
    {
        self::$packmask = $mask;
    }

    public function setOptions(Container $options): void
    {
        self::$name = ucfirst($options->get('Name'));
        self::$comment = $options->get('Comment');
        self::$private = $options->get('Password') != '';
        self::$maxPlayers = $options->get('CurrentMaxPlayers');
        self::$maxSpectators = $options->get('CurrentMaxSpectators');
        self::$ladderMode = $options->get('CurrentLadderMode');
        self::$vehicleNetQuality = $options->get('CurrentVehicleNetQuality');
        self::$callVoteTimeout = $options->get('CurrentCallVoteTimeOut');
        self::$callVoteRatio = $options->get('CallVoteRatio');
    }
}
