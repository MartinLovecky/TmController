<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Database;

enum Table: string
{
    case CHALLENGES = 'challenges';
    case PLAYERS = 'players';
    case RECORDS = 'records';
    case PLAYERS_EXTRA = 'players_extra';
    case RS_KARMA = 'rs_karma';
    case RS_RANK = 'rs_rank';
}
