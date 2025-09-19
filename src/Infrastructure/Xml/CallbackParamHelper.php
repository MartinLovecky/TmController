<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Infrastructure\Xml;

class CallbackParamHelper
{
    private static array $paramMap = [
        'TrackMania.PlayerChat'        => ['chatType', 'login', 'message'],
        'TrackMania.PlayerConnect'     => ['login', 'isSpectator'],
        'TrackMania.PlayerDisconnect'  => ['login'],
        'TrackMania.PlayerFinish'      => ['finishTime', 'login', 'rank'],
        'TrackMania.PlayerInfoChanged' => ['playerInfo'],
        'TrackMania.StatusChanged'     => ['statusCode', 'statusName'],
        'TrackMania.EndRace'           => ['playerInfo', 'challengeInfo'],
        'TrackMania.EndChallenge'      => ['playerInfo', 'challengeInfo', 'forceRestart', null, 'reservedFlag'],
        'TrackMania.PlayerCheckpoint'  => ['playerId', 'login', 'time', null, 'checkpointIndex'],
        'TrackMania.PlayerManialinkPageAnswer' => ['playerId', 'login', 'answerId']
    ];

    public static function getNamedParams(string $methodName): array
    {
        return self::$paramMap[$methodName] ?? [];
    }

    public static function setMapping(string $methodName, array $names): void
    {
        self::$paramMap[$methodName] = $names;
    }
}
