<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services;

class RaspState
{
    public array $chatvote = [];
    public array $plrvotes = [];
    public array $tmxadd = [];
    public array $jukebox = [];
    public bool $allow_spec_voting = false;
    public bool $ladder_fast_restart = true;
    public bool $feature_votes = true;
    public bool $feature_tmxadd = false;
    public string $tmxdir = '';
    public string $tmxtmpdir = '';

    public function __construct()
    {
        $this->tmxdir = 'Challenges' . DIRECTORY_SEPARATOR . 'TMX';
        $this->tmxtmpdir = 'Challenges' . DIRECTORY_SEPARATOR . 'TMXtmp';
    }
}
