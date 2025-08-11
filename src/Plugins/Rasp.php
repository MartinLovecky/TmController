<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

class Rasp
{
    public array $chatvote = [];
    public array $plrvotes = [];
    public array $tmxadd = [];
    public array $jukebox = [];
    public bool $allow_spec_voting = false;
    public bool $ladder_fast_restart = true;
    public bool $feature_votes = true;
    public bool $feature_tmxadd = false;
}
