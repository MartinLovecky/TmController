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

    public function onPlayerLink()
    {
        $this->eventManiaLink([]);
    }

    public function onNewChallenge(): void
    {
        $this->scorePanelOff();
    }

    public function onEndRace(): void
    {
        $this->scorePanelOn();
        $this->allWindowsOff();
    }

    public function onBeginRound(): void
    {
        $this->roundsPanelOff();
    }

    public function onPlayerFinish(Container $player): void
    {
        $this->roundsPanelOn($player);
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
        $style = $player->get('style');
        $templateVars = [
            'style'   => $style,
            'header'  => Aseco::validateUTF8($header),
            'icon'    => $icon,
            'data'    => $data,
            'widths'  => $widths,
            'button'  => Aseco::validateUTF8($button),
            'hsize'   => $style->get('header.textsize'),
            'bsize'   => $style->get('body.textsize'),
        ];

        $file = 'maniaLinks' . DIRECTORY_SEPARATOR . 'display';
        $xml = $this->widgetBuilder->render($file, $templateVars);
        $xml = str_replace('{#black}', $style->get('window.blackcolor'), $xml);

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
        $style = $player->get('style');

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

    public function displayManialinkMulti(Container $player): void
    {
        $this->eventManiaLink([0, $player, 1]);
    }

    //TODO $player->msgs and rest of this bs
    public function eventManiaLink(array $answer)
    {
        if ($answer[2] < -6 || $answer[2] > 36) {
            return;
        }

        /** @var Container $player */
        $player = $answer[1];
        $login = $player->get('Login');

        match ($answer[2]) {
            -6 => null, // rasp->chat_top100(['author' => $login])
            -5 => null, // rasp->chat_active(['author' => $login])
            -4 => null, //$player->set('msgs', 1),
            -3 => null, //$player->set('msgs', -5),
            -2 => null, //$player->set('msgs', -1),
            0  => $this->mainWindowOff($login),
            1  => null, // stay on current page
            2  => null, // $player->set('msgs', +1),
            3  => null, // $player->set('msgs', +5),
            4  => null, // $player->set('msgs', count($player->msgs)),
            5  => null, // chat.records2->chat_toprecs(['author' => $login])
            6  => null, // rasp->chat_topwins(['author' => $login])
            7  => null, // chat.records2->chat_topsums(['author' => $login])
            8  => null, // chat.records->chat_recs(['author' => $login, params => ''])
            9  => null, // chat.dedimania->chat_dedirecs(['author' => $login, params => ''])
            10 => null, // tmxInfo->chat_tmxrecs(['author' => $login, params => ''])
            11 => $this->mainWindowOff($login), //rasp.jukebox->chat_list(['author' => $login, params => 'env:Stadium'])
            // 12 => $this->mainWindowOff($login), rasp.jukebox->chat_list(['author' => $login, params => 'env:Alpine'])
            // 13 => $this->mainWindowOff($login), rasp.jukebox->chat_list(['author' => $login, params => 'env:Bay'])
            // 14 => $this->mainWindowOff($login), rasp.jukebox->chat_list(['author' => $login, params => 'env:Coast'])
            // 15 => $this->mainWindowOff($login), rasp.jukebox->chat_list(['author' => $login, params => 'env:Island'])
            // 16 => $this->mainWindowOff($login), rasp.jukebox->chat_list(['author' => $login, params => 'env:Rally'])
            // 17 => $this->mainWindowOff($login), rasp.jukebox->chat_list(['author' => $login, params => 'env:Speed'])
            18 => null, //ras->chat_y(['author' => $login])
            19 => null, //ignored
            20 => $this->mainWindowOff($login), //chatAdmin->chat_admin(['author' => $login, params => 'clearjukebox])
            21 => null, //chatAdmin->chat_admin(['author' => $login, params => 'restartmap])
            22 => null, //chatAdmin->chat_admin(['author' => $login, params => 'endround])
            23 => null, //chatAdmin->chat_admin(['author' => $login, params => 'nextmap])
            24 => null, //chatAdmin->chat_admin(['author' => $login, params => 'replaymap])
            25 => null, //chatAdmin->chat_admin(['author' => $login, params => 'pass])
            26 => null, //chatAdmin->chat_admin(['author' => $login, params => 'cancel])
            27 => null, //chatAdmin->chat_admin(['author' => $login, params => 'players live])
            28 => null, //donate->admin_pay($login, true)
            29 => null, //donate->admin_pay($login, false)
            30 => null, //donate->chat_donate(['author' => $login, params => $donation_values[0]])
            31 => null, //donate->chat_donate(['author' => $login, params => $donation_values[1]])
            32 => null, //donate->chat_donate(['author' => $login, params => $donation_values[2]])
            33 => null, //donate->chat_donate(['author' => $login, params => $donation_values[3]])
            34 => null, //donate->chat_donate(['author' => $login, params => $donation_values[4]])
            35 => null, //donate->chat_donate(['author' => $login, params => $donation_values[5]])
            36 => null, //donate->chat_donate(['author' => $login, params => $donation_values[6]])
            default => null
        };
        dd($player);
        $tot = count($player->get('msg'));

        $template = $this->widgetBuilder->render(
            'maniaLinks' . DIRECTORY_SEPARATOR . 'event_manialink',
            $this->buildData($player, $tot)
        );
        $xml = str_replace('{#black}', $player->get('style.window.blackcolor'), $template);
        $this->sendXmlToLogin($login, $xml);
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

    /**
     * sends xml created by twig to login
     *
     * @param string $login
     * @param string $file
     * @param array $vars
     * @param integer $timeout
     * @param boolean $hideOld
     * @return void
     */
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

    /**
     *
     * @param string $file
     * @param array $vars
     * @param integer $timeout
     * @param boolean $hideOld
     * @return void
     */
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

    private function buildData(Container $player, int $tot): array
    {
        $ptr = $player['msgs'][0][0];
        $header = Aseco::validateUTF8($player['msgs'][0][1]);
        $widths = $player['msgs'][0][2];
        $icon = $player['msgs'][0][3];
        $style = $player->get('style');
        $lines = $player['msgs'][$ptr];
        $linesCount = count($lines);

        if ($tot > 1) {
            $linesCount = max($linesCount, count($player['msgs'][1] ?? []));
        }

        return [
            'id' => 1,
            'ptr' => $ptr,
            'tot' => $tot,
            'header' => $header,
            'widths' => $widths,
            'icon' => $icon,
            'lines' => $lines,
            'linesCount' => $linesCount,
            'hsize' => $style->get('header.textsize'),
            'bsize' => $style->get('body.textsize'),
            'style' => $style,
            'hasPrev' => $ptr > 1,
            'hasNext' => $ptr < $tot,
            'add5' => $tot > 5,
        ];
    }
}
