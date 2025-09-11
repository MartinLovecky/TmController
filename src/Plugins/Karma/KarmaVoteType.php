<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

enum KarmaVoteType: string
{
    case PLUS               = 'plus';
    case PLUS_PLUS          = 'plusplus';
    case PLUS_PLUS_PLUS     = 'plusplusplus';
    case NONE               = 'none';
    case MINUS              = 'minus';
    case MINUS_MINUS        = 'minusminusminus';
    case MINUS_MINUS_MINUS  = 'minusminusminusminus';
    case HELP               = 'help';

    public function voteWeight(): int
    {
        return match ($this) {
            self::PLUS => 1,
            self::PLUS_PLUS => 2,
            self::PLUS_PLUS_PLUS => 3,
            self::NONE => 0,
            self::MINUS => -1,
            self::MINUS_MINUS => -2,
            self::MINUS_MINUS_MINUS => -3
        };
    }

    public function voteID(): int
    {
        return match ($this) {
            self::PLUS => 10,
            self::PLUS_PLUS => 11,
            self::PLUS_PLUS_PLUS => 18,
            self::NONE => 0,
            self::MINUS => 14,
            self::MINUS_MINUS => 15,
            self::MINUS_MINUS_MINUS => 16
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::PLUS => 'good',
            self::PLUS_PLUS => 'beautiful',
            self::PLUS_PLUS_PLUS => 'fantastic',
            self::NONE => 'none',
            self::MINUS => 'bad',
            self::MINUS_MINUS => 'poor',
            self::MINUS_MINUS_MINUS => 'waste'
        };
    }
}
