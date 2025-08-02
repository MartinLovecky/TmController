<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Envms\FluentPDO\Query;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\App\WidgetBuilder;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\PlayerService;

class ManiaLinks
{
    public function __construct(
        protected Client $client,
        protected PlayerService $playerService,
        protected WidgetBuilder $widgetBuilder,
    ) {
    }

    /**
     * Displays a single ManiaLink window to a player
     *
     * @param string $login player login to send window to
     * @param string $header
     * @param array $icon
     * @param array $data
     * @param array $widths
     * @param string $button
     * @return void
     */
    public function displayManialink(
        string $login,
        string $header,
        array $icon,
        array $data,
        array $widths,
        string $button
    ): void {
        $player = $this->playerService->getPlayerByLogin($login);
        $style = $player->get('style', '');
        $templateVars = [
            'style'   => $style,
            'header'  => Aseco::validateUTF8($header),
            'icon'    => $icon,
            'data'    => $data,
            'widths'  => $widths,
            'button'  => Aseco::validateUTF8($button),
        ];

        // TMN - style fallback setup
        if (empty($style)) {
            $tsp = 'B';
            $templateVars['tsp'] = $tsp;
        } else {
            // TMF-style: extract header/body text sizes
            $templateVars['hsize'] = $style->get('header.textsize', 0);
            $templateVars['bsize'] = $style->get('body.textsize', 0);
        }

        $file = 'maniaLinks' . DIRECTORY_SEPARATOR . 'display';
        $xml = $this->widgetBuilder->render($file, $templateVars);
        $maniaLink = str_replace('{#black}', $style->get('window.blackcolor', ''), $xml);

        $this->client->query('SendDisplayManialinkPageToLogin', [
            $login,
            Aseco::formatColors($maniaLink),
            0,
            true
        ]);
    }

    /**
     * Displays custom TMX track ManiaLink window to a player
     *
     * @param string $login
     * @param string $header
     * @param array $icon
     * @param array $links
     * @param array $data
     * @param array $widths
     * @param string $button
     * @return void
     */
    public function displayManialinkTrack(
        string $login,
        string $header,
        array $icon,
        array $links,
        array $data,
        array $widths,
        string $button
    ): void {
        $player = $this->playerService->getPlayerByLogin($login);
        $style = $player->get('style', '');
        $square = $links[1];
        $lines = count($data);
        $templateVars = [
            'header'  => $header,
            'icon'    => $icon,
            'links'   => $links,
            'data'    => $data,
            'widths'  => $widths,
            'button'  => $button,
            'square'  => $square,
            'style'   => $style,
            'lines'   => $lines,
            'hsize'   => $style->get('header.tetxsize', 3),
            'bsize'   => $style->get('body.textsize', 2)
        ];

        $file = 'maniaLinks' . DIRECTORY_SEPARATOR . 'display_track';
        $maniaLink = $this->widgetBuilder->render($file, $templateVars);

        $this->client->query('SendDisplayManialinkPageToLogin', [
            $login,
            Aseco::formatColors($maniaLink),
            0,
            true
        ]);
    }

    public function displayManialinkMulti()
    {
    }

    //TODO pain
    public function eventManiaLink()
    {
    }

    public function displayPayment(
        string $login,
        string $serverName,
        string $label
    ): void {
        $templateVars = [
            'server' => $serverName,
            'label' => $label
        ];
        $file = 'maniaLinks' . DIRECTORY_SEPARATOR . 'payment';
        $maniaLink = $this->widgetBuilder->render($file);

        $this->client->query('SendDisplayManialinkPageToLogin', [$login, $maniaLink, 0, true]);
    }

    public function mainWindowOff(string $login): void
    {
        $xml = '<manialink id="1"></manialink>';
        $this->client->query('SendDisplayManialinkPageToLogin', [$login, $xml, 0, true]);
    }

    public function allWindowsOff(): void
    {
        $file = 'maniaLinks' . DIRECTORY_SEPARATOR . 'off';
        $xml = $this->widgetBuilder->render($file);
        $this->client->query('SendDisplayManialinkPage', [$xml, 0, false]);
    }
}
