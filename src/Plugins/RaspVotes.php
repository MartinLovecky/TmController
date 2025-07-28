<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Infrastructure\Gbx\Client;

class RaspVotes
{
    private array $chatvote = [];
    private int $num_laddervotes = 0;
    private int $num_replayvotes = 0;
    private int $num_skipvotes = 0;

    public function __construct(protected Client $client)
    {
        // Array of structs [[[]]]
        // Struct in array [[]]
        $this->client->query('SetCallVoteRatios', [[['Command' => '*', 'Ratio' => -1.0]]]);
        $this->resetVotes();
    }

    private function resetVotes(): void
    {
        if (!empty($this->chatvote)) {
            Aseco::console(
                'Vote by {1} to {2} reset!',
                $this->chatvote['login'] ?? '', // $chatvote['login'],
                'End this Round' // $chatvote['desc']
            );
        }

        $message = Aseco::getChatMessage('vote_cancel', 'rasp');
        $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);

        $xml = '<manialink id="5"></manialink>';
        $this->client->query('SendDisplayManialinkPage', [
            'manialink' => $xml,
            'duration' => 0,
            'display' => false
        ]);

        $this->num_laddervotes = 0;
        $this->num_replayvotes = 0;
        $this->num_skipvotes = 0;
    }
}
