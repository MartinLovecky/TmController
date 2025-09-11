<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Rasp;

class RaspVoteDTO
{
    public ?string $login = null;
    public ?string $nick = null;
    public ?int $votes = null;
    public ?string $type = null;
    public ?string $desc = null;
    public ?string $target = null;

    public function __construct(array $data)
    {
        $this->login = $data['login'];
        $this->nick = $data['nick'];
        $this->votes = $data['votes'];
        $this->type = $data['type'];
        $this->desc = $data['desc'];
        $this->target = $data['target'] ?? null;
    }
}
