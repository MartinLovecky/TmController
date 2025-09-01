<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Rasp;

/**
 * Enum representing different types of votes.
 */
enum RaspVoteType: string
{
    case ENDROUND = 'endround';
    case LADDER   = 'ladder';
    case REPLAY   = 'replay';
    case SKIP     = 'skip';
    case KICK     = 'kick';
    case IGNORE   = 'ignore';
    case CANCEL   = 'cancel';
    case HELP     = 'help';

    /**
     * Returns description of the vote type.
     */
    public function description(): string
    {
        return match ($this) {
            self::ENDROUND => 'End this Round',
            self::LADDER   => 'Enable/Disable Ladder',
            self::REPLAY   => 'Replay this Track',
            self::SKIP     => 'Skip this Track',
            self::KICK     => 'Kick Player',
            self::IGNORE   => 'Ignore Player',
            self::CANCEL   => 'Cancel Current Vote',
        };
    }

    /**
     * Returns the default ratio required to pass this vote type.
     */
    public function defaultRatio(): float
    {
        return match ($this) {
            self::ENDROUND => 0.6,
            self::LADDER   => 0.7,
            self::REPLAY   => 0.5,
            self::SKIP     => 0.5,
            self::KICK     => 0.75,
            self::IGNORE   => 0.75,
            self::CANCEL   => 0.0,
        };
    }

    /**
     * Returns a display-friendly string of the vote type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ENDROUND => 'End Round',
            self::LADDER   => 'Ladder',
            self::REPLAY   => 'Replay',
            self::SKIP     => 'Skip',
            self::KICK     => 'Kick',
            self::IGNORE   => 'Ignore',
            self::CANCEL   => 'cancel',
        };
    }
}
