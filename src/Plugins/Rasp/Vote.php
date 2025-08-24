<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Rasp;

class Vote
{
    public string $login;
    public string $nick;
    public int $votes;
    public string $type;
    public string $desc;
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
