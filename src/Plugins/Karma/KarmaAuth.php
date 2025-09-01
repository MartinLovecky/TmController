<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

use Yuhzel\TmController\App\Service\{Aseco, HttpClient, Server};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Infrastructure\Xml\Parser;

class KarmaAuth
{
    public string $api = 'http://worldwide.mania-karma.com/api/tmforever-trackmania-v4.php';
    public int $retryTime = 0;
    public int $retryWait = 600;
    public bool $importDone = false;
    public ?string $mode = '';

    public function __construct(private HttpClient $httpClient)
    {
    }

    public function authenticate(): void
    {
        if (!isset($_ENV['authcode'])) {
            $this->performAuthentication();
        } else {
            Aseco::console(' => Successfully started with async communication.');
        }
    }

    private function performAuthentication(): void
    {
        $response = $this->httpClient->get($this->api, [
            'Action' => 'Auth',
            'login' => $_ENV['server_login'],
            'name' => Server::$name,
            'game' => Server::$game,
            'zone' => Server::$zone,
            'nation' => $_ENV['dedi_nation']
        ]);

        $output = Parser::fromXMLString($response);

        if ($output->get('status') !== 200) {
            $this->handleAuthenticationFailure($output);
        } else {
            $this->handleAuthenticationSuccess($output);
        }
    }

    private function handleAuthenticationFailure(TmContainer $output): void
    {
        $this->mode = 'local';
        $this->retryTime = time() + $this->retryWait;

        Aseco::console(' => Connection failed with {1} for url {2}', $output->get('status'), $this->api);
        Aseco::console('Please check Auth variables');
    }

    private function handleAuthenticationSuccess(TmContainer $output): void
    {
        $this->mode = 'global';
        $this->importDone = $output->get('import_done');
        Aseco::updateEnvFile('KarmaAPI', $output->get('api_url'));
        Aseco::updateEnvFile('KarmaHash', $output->get('authcode'));

        Aseco::console(' => Successfully started with async communication.');
        Aseco::console(' => API Request-URL: {1}', $output->get('api_url'));
    }

    public function shouldRetry(): bool
    {
        return $this->retryTime > 0 && time() >= $this->retryTime;
    }
}
