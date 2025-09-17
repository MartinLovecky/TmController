<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Server;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Infrastructure\Xml\Parser;
use Yuhzel\TmController\Repository\Player\PlayerService;

class TmController
{
    public readonly Container $config;
    public Container $adminOps;
    public ?Container $bannedIps = null;

    public function __construct(
        protected Client $client,
        protected Server $server,
        protected PlayerService $playerService
    ) {
    }

    public function run()
    {
        $this->readConfig(); // config is "immutable"
        //TODO: update adminops.xml file to preserve change
        $this->readAdminOps();  // adminOps can be added/removed
        $this->readBannedIps(); // we could set empty container but null is better

        //connect to server
        $this->connect();
    }

    private function readConfig(): void
    {
        $conf = Aseco::path() . 'public/xml/config.xml';
        Aseco::console('[X8seco] Load settings from [{1}]', $conf);
        $this->config = Parser::fromFile('config', true);
    }

    private function readAdminOps(): void
    {
        $ops = Aseco::path() . 'public/xml/adminops.xml';
        Aseco::console('[X8seco] Load admin/ops lists [{1}]', $ops);
        // We want be able mod list of admins
        $this->adminOps = Parser::fromFile($this->config->get('aseco.adminops_file'), false);
    }

    private function readBannedIps(): void
    {
        $bannedIps = Parser::fromFile($this->config->get('aseco.bannedips_file'), false);
        if (!$bannedIps->isEmpty()) {
            $ipsFile = Aseco::path() . 'public/xml/bannedips.xml';
            Aseco::console('[X8seco] Load banned IPs list [{1}]', $ipsFile);
            $this->bannedIps = $bannedIps;
        } else {
            unset($bannedIps);
        }
    }

    private function connect()
    {
        Aseco::console(
            'Trying to connect to TM dedicated server on {1}:{2} timeout {3}s',
            $this->server->ip,
            $this->server->port,
            $this->server->timeout
        );

        Aseco::console('Trying to authenticate with login {1}', $this->server->login);

        dd($this->playerService);
    }
}
