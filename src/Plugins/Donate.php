<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Aseco, Log, Sender, Server};
use Yuhzel\TmController\Core\TmContainer;

class Donate
{
    public array $bills = [];
    public array $payments = [];
    public int $minDonation = 10;
    public int $publicappr = 100;
    public array $values = [20, 50, 100, 200, 500, 1000, 2000];

    public function __construct(private Sender $sender)
    {
    }

    public function chatDonate(TmContainer $player, int $coppers = 0): void
    {
        $login = $player->get('Login');

        if ($player->get('OnlineRights') !== 3) {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('united_only'),
                formatArgs: ['account'],
                formatMode: Sender::FORMAT_BOTH
            );
            return;
        }

        if ($coppers <= 0 || !is_numeric($coppers)) {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('donate_help'),
                formatMode: Sender::FORMAT_COLORS
            );
            return;
        }

        if ($coppers <= $this->minDonation) {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('donate_minimum'),
                formatArgs: [$this->minDonation],
                formatMode: Sender::FORMAT_BOTH
            );
            return;
        }

        $billid = $this->sender->query(
            method: 'SendBill',
            params: [
                $login,
                $coppers,
                Aseco::getChatMessage('donation'),
                ''
            ],
            formatArgs: [$coppers, Server::$name, ''],
            formatMode: Sender::FORMAT_BOTH
        );

        Log::debug('coppers', $billid->toArray(), 'donate');
        $this->bills[$billid] = [$login, $player->get('NickName'), $coppers];
    }

    public function adminPayment(string $login, ?string $target = null, int $amount = 0)
    {
        if (!$target && $amount === 0) {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('pay_help'),
                formatMode: Sender::FORMAT_COLORS
            );
            return;
        }
    }

    public function adminPay(string $login, bool $answer): void
    {
        if (!$answer) {
            $this->sender->sendChatMessageToLogin(
                login: $login,
                message: Aseco::getChatMessage('pay_cancel'),
                formatArgs: [$this->payments[$login][0] ?? 0],
                formatMode: Sender::FORMAT_BOTH
            );
            return;
        }

        $billid = $this->sender->query(
            method:'Pay',
            params:[
                $this->payments[$login][0],
                $this->payments[$login][1],
                $this->payments[$login][2]
            ],
            formatMode: Sender::FORMAT_COLORS
        );
        Log::debug('coppers', $billid->toArray(), 'payment');
        $this->bills[$billid] = [$login, $this->payments[$login][0], -$this->payments[$login][1]];
    }

    public function onBillUpdated()
    {
    }
}
