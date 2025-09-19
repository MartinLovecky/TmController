<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Enums;

enum Status: int
{
    case NONE = 0;
    case LAUNCHING = 2;
    case RUNNING_SYNC = 3;
    case RUNNING_PLAY = 4;
    case FINISH = 5;
    case WAITING_FOR_CHALLENGE = 100;
}
