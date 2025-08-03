<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\{Aseco, WidgetBuilder};
use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Infrastructure\Gbx\Client;
use Yuhzel\TmController\Repository\PlayerService;

class ManiaLinks
{
    private array $mlRecords = ['local' => '   --.--', 'dedi' => '   --.--', 'tmx' => '   --.--'];

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
        $xml = str_replace('{#black}', $style->get('window.blackcolor', ''), $xml);

        $this->sendXmlToLogin($login, Aseco::formatColors($xml), 0, true);
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

        $vars = [
            'header' => Aseco::validateUTF8($header),
            'icon' => $icon,
            'links' => $links,
            'data' => $data,
            'widths' => $widths,
            'button' => Aseco::validateUTF8($button),
            'square' => $links[1],
            'style' => $style,
            'lines' => count($data),
            'hsize' => $style->get('header.textsize', 3),
            'bsize' => $style->get('body.textsize', 2),
        ];

        $this->renderAndSendToLogin($login, 'display_track', $vars, 0, true);
    }

    //TODO pain
    public function displayManialinkMulti()
    {
    }

    //TODO pain
    public function eventManiaLink()
    {
    }

    public function displayPayment(string $login, string $serverName, string $label): void
    {
        $this->renderAndSendToLogin($login, 'payment', ['server' => $serverName, 'label' => $label]);
    }

    public function mainWindowOff(string $login): void
    {
        $this->sendXmlToLogin($login, '<manialink id="1"></manialink>');
    }

    public function allWindowsOff(): void
    {
        $this->renderAndSendToAll('off');
    }

    public function displayCpsPanel(string $login, int $cp, string $diff): void
    {
        $this->renderAndSendToLogin($login, 'cps_panel', ['cp' => $cp, 'diff' => $diff]);
    }

    public function cpsPanelOff(string $login): void
    {
        $this->sendXmlToLogin($login, '<manialink id="2"></manialink>');
    }

    public function allCpsPanelsOff(): void
    {
        $this->sendXmlToAll('<manialink id="2"></manialink>');
    }

    public function displayAdmPanel(Container $player): void
    {
        $this->sendXmlToLogin($player->get('Login'), $player->get('panels.admin'));
    }

    public function admPanelOff(string $login): void
    {
        $this->sendXmlToLogin($login, '<manialink id="3"></manialink>');
    }

    public function displayDonPanel(Container $player, array $coppers): void
    {
        $xml = $player->get('panels.donate');
        for ($i = 1; $i <= 7; $i++) {
            $xml = str_replace('%COP' . $i . '%', $coppers[$i - 1], $xml);
        }

        $this->sendXmlToLogin($player->get('Login'), $xml);
    }

    public function donPanelOff(string $login): void
    {
        $this->sendXmlToLogin($login, '<manialink id="6"></manialink>');
    }

    public function displayRecPanel(Container $player, $pb): void
    {
        $xml = str_replace(
            ['%PB%', '%TMX%', '%LCL%', '%DED%'],
            [$pb, $this->mlRecords['tmx'], $this->mlRecords['local'], $this->mlRecords['dedi']],
            $player->get('panels.records')
        );

        $this->sendXmlToLogin($player->get('Login'), $xml);
    }

    public function recPanelOff(string $login): void
    {
        $this->sendXmlToLogin($login, '<manialink id="4"></manialink>');
    }

    public function displayVotePanel(
        Container $player,
        string $yes,
        string $no,
        int $timeout = 0
    ): void {
        $xml = str_replace(
            ['%YES%', '%NO%'],
            [$yes, $no],
            $player->get('panels.vote')
        );

        $this->sendXmlToLogin($player->get('Login'), $xml, $timeout);
    }

    public function votePanelOff(string $login): void
    {
        $this->sendXmlToLogin($login, '<manialink id="5"></manialink>');
    }

    public function allVotePanelsOff(): void
    {
        $this->sendXmlToAll('<manialink id="5"></manialink>');
    }

    public function displayMsgWindow(array $msgs, int $timeout = 0): void
    {
        $validated = array_map([Aseco::class, 'validateUTF8'], $msgs);
        $this->renderAndSendToAll('msgwindow', ['msgs' => $validated], $timeout);
    }

    public function msgLogButton(string $login): void
    {
        $this->renderAndSendToLogin($login, 'log_button');
    }

    //display_statspanel |statspanels_off -> disabled

    public function scorePanelOff(bool $panel = true): void
    {
        if (!$panel) {
            $this->renderAndSendToAll('costum_ui', ['ui' => '<scoretable visible="False"/>']);
        }
    }

    public function scorePanelOn(): void
    {
        $this->renderAndSendToAll('costum_ui', ['ui' => '<scoretable visible="True"/>']);
    }

    public function roundsPanelOff(bool $panel = true): void
    {
        if (!$panel) {
            $this->renderAndSendToAll('costum_ui', ['ui' => '<round_scores visible="False"/>']);
        }
    }

    public function roundsPanelOn(Container $player): void
    {
        $this->renderAndSendToLogin($player->get('Login'), 'costum_ui', ['ui' => '<round_scores visible="True"/>']);
    }

    private function renderAndSendToLogin(
        string $login,
        string $file,
        array $vars = [],
        int $timeout = 0,
        bool $hideOld = false
    ): void {
        $xml = $this->widgetBuilder->render('maniaLinks' . DIRECTORY_SEPARATOR . $file, $vars);
        $this->client->query('SendDisplayManialinkPageToLogin', [
        $login,
        Aseco::formatColors($xml),
        $timeout,
        $hideOld
        ]);
    }

    private function renderAndSendToAll(
        string $file,
        array $vars = [],
        int $timeout = 0,
        bool $hideOld = false
    ): void {
        $xml = $this->widgetBuilder->render('maniaLinks' . DIRECTORY_SEPARATOR . $file, $vars);
        $this->client->query('SendDisplayManialinkPage', [$xml, $timeout, $hideOld]);
    }

    private function sendXmlToLogin(
        string $login,
        string $xml,
        int $timeout = 0,
        bool $hideOld = false
    ): void {
        $this->client->query('SendDisplayManialinkPageToLogin', [$login, $xml, $timeout, $hideOld]);
    }

    private function sendXmlToAll(
        string $xml,
        int $timeout = 0,
        bool $hideOld = false
    ): void {
        $this->client->query('SendDisplayManialinkPage', [$xml, $timeout, $hideOld]);
    }
}
