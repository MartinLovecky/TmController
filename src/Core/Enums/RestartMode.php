<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Enums;

enum RestartMode
{
    case NONE;
    case QUICK;
    case FULL;
}
