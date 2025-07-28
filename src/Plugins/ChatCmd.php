<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\App\Command;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\{ChallengeService, RecordService};

class ChatCmd
{
    private array $commands = [];

    public function __construct(
        protected Client $client,
        protected ChallengeService $challengeService,
        protected RecordService $recordService
    ) {
        $this->commands = [
            ['help', [$this, 'help'], 'Shows all available commands'],
            ['helpall', [$this, 'helpPall'], 'Displays help for available commands'],
            ['laston',  [$this, 'laston'], 'Shows when a player was last online'],
            ['lastwin', [$this, 'lastwin'], 'Re-opens the last closed multi-page window'],
            ['me',  [$this, 'me'], 'Can be used to express emotions'],
            ['wins',  [$this, 'wins'], 'Shows wins for current player'],
            ['song', [$this, 'song'], 'Shows filename of current track\'s song'],
            ['mod',  [$this, 'mod'], 'Shows (file)name of current track\'s mod'],
            ['players', [$this, 'players'], 'Displays current list of nicks/logins'],
            ['ranks',  [$this, 'ranks'], 'Displays list of online ranks/nicks'],
            ['clans',  [$this, 'clans'], 'Displays list of online clans/nicks'],
            ['topclans', [$this, 'topclans'], 'Displays top 10 best ranked clans'],
            ['recs', [$this, 'recs'], 'Displays all records on current track'],
            ['best', [$this, 'best'], 'Displays your best records'],
            ['worst', [$this, 'worst'], 'Displays your worst records'],
            ['summary',  [$this, 'summary'], 'Shows summary of all your records'],
            ['topsums', [$this, 'topsums'], 'Displays top 100 of top-3 record holders'],
            ['toprecs',  [$this, 'toprecs'], 'Displays top 100 ranked records holders'],
            ['newrecs',  [$this, 'newrecs'], 'Shows newly driven records'],
            ['liverecs', [$this, 'liverecs'], 'Shows records of online players'],
            ['firstrec', [$this, 'firstrec'], 'Shows first ranked record on current track'],
            ['lastrec',  [$this, 'lastrec'], 'Shows last ranked record on current track'],
            ['nextrec', [$this, 'nextrec'], 'Shows next better ranked record to beat'],
            ['diffrec', [$this, 'diffrec'], 'Shows your difference to first ranked record'],
            ['recrange', [$this, 'recrange'], 'Shows difference first to last ranked record'],
            ['server', [$this, 'server'], 'Displays info about this server'],
            ['xaseco',  [$this, 'xaseco'], 'Displays info about this XASECO'],
            ['plugins',  [$this, 'plugins'], 'Displays list of active plugins'],
            ['nations',  [$this, 'nations'], 'Displays top 10 most visiting nations'],
            ['stats',  [$this, 'stats'], 'Displays statistics of current player'],
            ['statsall', [$this, 'statsall'], 'Displays world statistics of a player'],
            ['settings',  [$this, 'settings'], 'Displays your personal settings']
        ];
        Command::register($this->commands, 'ChatCmd');
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
