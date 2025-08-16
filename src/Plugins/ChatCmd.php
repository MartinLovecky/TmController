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
    //TODO : this is mess in old code
    public function trackrecs(?string $login = null, string $mode): void
    {
        $tmxUrl = $this->challengeService->getTMX()->pageurl;
        $challName = $this->challengeService->getGBX()->name;
        $name = '$l[' . $tmxUrl . ']' . Aseco::stripColors($challName) . '$l';

        if (!isset($login)) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('ranking'),
                $name,
                $mode
            );
            $this->client->query('ChatSendServerMessage', [Aseco::formatColors($message)]);
            return;
        }

        $records = $this->recordService->getRecordForPlayer(
            $this->challengeService->getUid(),
            $login
        );

        if (empty($records)) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('ranking_none'),
                $name,
                'before'
            );
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message),
                $login
            ]);
            Aseco::console($message);
            return;
        } else {
            dd($records);
        }
    }
}
