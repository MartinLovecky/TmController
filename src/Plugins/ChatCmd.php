<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\App\Service\{Aseco, Sender};
use Yuhzel\TmController\Repository\{ChallengeService, RecordService};

class ChatCmd
{
    public function __construct(
        private ChallengeService $challengeService,
        private RecordService $recordService,
        private Sender $sender,
    ) {
    }

    //TODO : this is mess in old code
    public function trackrecs(?string $login = null, string $mode): null
    {
        $tmxUrl = $this->challengeService->getTMX()->pageurl;
        $challName = $this->challengeService->getGBX()->name;
        $name = '$l[' . $tmxUrl . ']' . Aseco::stripColors($challName) . '$l';

        if (!isset($login)) {
            return $this->sender->sendChatMessageToAll(
                message: Aseco::getChatMessage('ranking'),
                formatArgs: [$name, $mode],
                formatMode: Sender::FORMAT_BOTH
            );
        }

        $records = $this->recordService->getRecordForPlayer($this->challengeService->getUid(), $login);

        if (empty($records)) {
            return $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('ranking_none'),
                formatArgs: [$name, 'before'],
                formatMode: Sender::FORMAT_BOTH
            );
        } else {
            dd($records);
        }
    }

    public function getRecs(): TmContainer
    {
        return $this->recordService->fetchSingle($this->challengeService->getUid());
    }

    public function chatRecs()
    {
    }
}
