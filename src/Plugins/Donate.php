<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\App\Service\Log;
use Yuhzel\TmController\App\Service\Server;
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Infrastructure\Gbx\Client;

class Donate
{
    public array $bills = [];
    public array $payments = [];
    public int $minDonation = 10;
    public int $publicappr = 100;
    public array $values = [20, 50, 100, 200, 500, 1000, 2000];

    public function __construct(protected Client $client)
    {
    }

    public function chatDonate(TmContainer $player, int $coppers = 0): void
    {
        $login = $player->get('Login');

        if ($player->get('OnlineRights') !== 3) {
            $message = Aseco::formatText(Aseco::getChatMessage('united_only'), 'account');
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message),
                $login
            ]);
            return;
        }

        if ($coppers <= 0 || !is_numeric($coppers)) {
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors(Aseco::getChatMessage('donate_help')),
                $login
            ]);
            return;
        }

        if ($coppers <= $this->minDonation) {
            $message = Aseco::formatText(Aseco::getChatMessage('donate_minimum'), $this->minDonation);
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message),
                $login
            ]);
            return;
        }

        $message = Aseco::formatText(
            Aseco::getChatMessage('donation'),
            $coppers,
            Server::$name,
            ''
        );

        $billid = $this->client->query('SendBill', [
            $login,
            $coppers,
            Aseco::formatColors($message),
            ''
        ]);

        Log::debug('coppers', $billid->toArray(), 'donate');
        $this->bills[$billid] = [$login, $player->get('NickName'), $coppers];
    }

    public function adminPayment(string $login, ?string $target = null, int $amount = 0)
    {
        if (!$target && $amount === 0) {
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors(Aseco::getChatMessage('pay_help')),
                $login
            ]);
            return;
        }
    }

    public function adminPay(string $login, bool $answer): void
    {
        if (!$answer) {
            $message = Aseco::formatText(
                Aseco::getChatMessage('pay_cancel'),
                $this->payments[$login][0] ?? 0
            );
            $this->client->query('ChatSendServerMessageToLogin', [
                Aseco::formatColors($message),
                $login
            ]);
            return;
        }

        $billid = $this->client->query('Pay', [
            $this->payments[$login][0],
            $this->payments[$login][1],
            Aseco::formatColors($this->payments[$login][2])
        ]);
        Log::debug('coppers', $billid->toArray(), 'payment');
        $this->bills[$billid] = [$login, $this->payments[$login][0], -$this->payments[$login][1]];
    }

    public function onBillUpdated()
    {
    }
}
