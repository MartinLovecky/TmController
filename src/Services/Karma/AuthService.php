<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services\Karma;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Services\Server;
use Yuhzel\TmController\Services\HttpClient;
use Yuhzel\TmController\Infrastructure\Xml\Parser;

class AuthService
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
            Aseco::console('**********************************************************');
        }
    }

    public function shouldRetry(): bool
    {
        return $this->retryTime > 0;
    }

    private function performAuthentication(): void
    {
        $response = $this->httpClient->get($this->api, $this->getAuthParams());

        $output = Parser::fromXMLString($response)->setReadonly();

        if ($output->get('status') !== 200) {
            $this->handleAuthenticationFailure($output);
        } else {
            $this->handleAuthenticationSuccess($output);
        }
    }

    private function getAuthParams(): array
    {
        return [
           'Action' => 'Auth',
           'login' => $_ENV['server_login'],
           'name' => Server::$name,
           'game' => Server::$game,
           'zone' => Server::$zone,
           'nation' => $_ENV['dedi_nation'],
        ];
    }

    private function handleAuthenticationFailure(Container $output): void
    {
        $this->mode = 'local';
        $this->retryTime = time() + $this->retryWait;

        Aseco::console(
            ' => Connection failed with {1} for url {2}',
            $output->get('status'),
            $this->api
        );
        Aseco::console("Please check Auth variables");
        Aseco::console('**********************************************************');
    }

    private function handleAuthenticationSuccess(Container $output): void
    {
        $this->mode = 'global';
        $this->importDone = $output->get('import_done');
        Aseco::updateEnvFile('KarmaAPI', $output->get('api_url'));
        Aseco::updateEnvFile('KarmaHash', $output->get('authcode'));

        Aseco::console(' => Successfully started with async communication.');
        Aseco::console(' => The API set the Request-URL to: {1}', $output->get('api_url'));
        Aseco::console('**********************************************************');
    }
}
