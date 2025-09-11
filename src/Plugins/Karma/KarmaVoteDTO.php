<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

class KarmaVoteDTO
{
    public ?string $login = null;
    public ?string $type = null;
    public ?string $label = null;
    public array $votes = []; // [3,2,1,0 ...]
    public int $count = 0;

    public function __construct(array $data)
    {
        $this->login = $data['login'];
        $this->votes = $data['votes'];
        $this->type = $data['type'];
        $this->label = $data['label'];
        $this->count = count($this->votes);
    }

    public function addVote(KarmaVoteType $voteType): bool
    {
        if (!empty($this->votes)) {
            $lastVote = end($this->votes);
            if ($lastVote['type'] === $voteType->value) {
                return false; // disallow same vote twice in a row
            }
        }
        $this->votes[] = [
            'type'   => $voteType->value,
            'weight' => $voteType->voteWeight(),
        ];

        $this->count = count($this->votes);
        $this->type = $voteType->value;
        $this->label = $voteType->label();
        return true;
    }
}
