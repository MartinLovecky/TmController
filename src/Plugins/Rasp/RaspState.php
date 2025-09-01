<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Rasp;

use Yuhzel\TmController\Plugins\Rasp\RaspVoteDTO;

class RaspState
{
    public ?RaspVoteDTO $chatvote = null;
    public array $plrvotes = [];
    public array $tmxadd = [];
    public array $jukebox = [];
    public bool $allow_spec_voting = false;
    public bool $ladder_fast_restart = true;
    public bool $disabled_scoreboard = true;
    public bool $allow_spec_startvote = false;
    public bool $feature_votes = true;
    public bool $feature_tmxadd = false;
    public bool $ta_time_limits = false;
    public bool $r_points_limits = false;
    public bool $allow_kickvotes = false;
    public bool $allow_admin_kick = false;
    public bool $allow_ignorevotes = false;
    public string $tmxdir = '';
    public string $tmxtmpdir = '';
    public int $num_laddervotes = 0;
    public int $replays_limit = 0;
    public int $replays_counter = 0;
    public int $max_skipvotes = 1;
    public int $max_replayvotes = 2;
    public int $r_expire_num = 0;
    public int $ta_show_num = 0;
    public int $ta_expire_start = 0;
    public int $num_skipvotes = 0;
    public float $r_skip_max = 0.5;
    public float $r_ladder_max = 0.4;
    public float $ta_skip_max = 1.0;
    public float $ta_ladder_max = 0.4;
    public int $max_laddervotes = 0;

    public function __construct()
    {
        $this->tmxdir = 'Challenges' . DIRECTORY_SEPARATOR . 'TMX';
        $this->tmxtmpdir = 'Challenges' . DIRECTORY_SEPARATOR . 'TMXtmp';
    }
}
