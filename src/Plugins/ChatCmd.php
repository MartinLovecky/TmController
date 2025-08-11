<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\{ChallengeService, RecordService};

class ChatCmd
{
    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected RecordService $recordService
    ) {
    }

    public function trackrecs()
    {
        $records = $this->recordService->getRecord()->get('Times');
        $tmxUrl = $this->challengeService->getTMX()->pageurl;
        $challName = $this->challengeService->getGBX()->name;
        $name = '$l[' . $tmxUrl . ']' . Aseco::stripColors($challName) . '$l';

        if ($records->isEmpty()) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('ranking_none'),
                $name,
                'before'
            );
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message), //$player->get('Login')
            ]);
            Aseco::console($message);
            return;
        } else {
            dd($records);
        }
    }
}
