<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Service\{Arr, Aseco, Log, Sender};
use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Plugins\{Donate, ChatAdmin, RaspJukebox};
use Yuhzel\TmController\Plugins\Manager\{PageListManager, PluginManager};
use Yuhzel\TmController\Repository\PlayerService;

class ManiaLinks
{
    public array $mlRecords = ['local' => '   --.--', 'dedi' => '   --.--', 'tmx' => '   --.--'];
    private string $path = '';
    protected ?Donate $donate = null;
    protected ?ChatAdmin $chatAdmin = null;
    protected ?RaspJukebox $raspJukebox = null;

    public function __construct(
        private PageListManager $pageListManager,
        private PlayerService $playerService,
        private Sender $sender
    ) {
        $this->path = 'maniaLinks' . DIRECTORY_SEPARATOR;
    }

    public function setRegistry(PluginManager $registry): void
    {
        $this->donate = $registry->donate;
        $this->chatAdmin = $registry->chatAdmin;
        $this->raspJukebox = $registry->raspJukebox;
    }

    public function onPlayerLink(TmContainer $manialink)
    {
        $this->eventManiaLink($manialink);
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

    public function onPlayerFinish(TmContainer $player): void
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

        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->path}display",
            context: $templateVars,
            formatMode: Sender::FORMAT_COLORS,
            hideOnEsc: true
        );
        //$xml = str_replace('{#black}', $style->get('window.blackcolor'), $xml);
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

        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->path}display_track",
            context: $vars,
            formatMode: Sender::FORMAT_COLORS,
            hideOnEsc: true
        );
    }

    public function eventManiaLink(TmContainer $manialink): void
    {
        $message = $manialink->get('message');
        if ($message < -6 || $message > 36) {
            return;
        }

        $login = $manialink->get('login');
        $player = $this->playerService->getPlayerByLogin($login);

        if (!$player) {
            return;
        }

        match ($message) {
            -6 => null, // rasp->chat_top100(['author' => $login])
            -5 => null, // rasp->chat_active(['author' => $login])
            -4 => $this->pageListManager->navigate($login, $message),
            -3 => $this->pageListManager->navigate($login, $message),
            -2 => $this->pageListManager->navigate($login, $message),
            0  => $this->mainWindowOff($login),
            1  => $this->pageListManager->navigate($login, $message), // stays on current page
            2  => $this->pageListManager->navigate($login, $message),
            3  => $this->pageListManager->navigate($login, $message),
            4  => $this->pageListManager->goLastPage($login),
            5  => null, // chat.records2->chat_toprecs(['author' => $login])
            6  => null, // rasp->chat_topwins(['author' => $login])
            7  => null, // chat.records2->chat_topsums(['author' => $login])
            8  => null, // chat.records->chat_recs(['author' => $login, params => ''])
            9  => null, // chat.dedimania->chat_dedirecs(['author' => $login, params => ''])
            10 => null, // tmxInfo->chat_tmxrecs(['author' => $login, params => ''])
            11 => $this->mainWindowAndJukebox($login),
            12 => Log::info('DISABLED env Alpine', channel:'event'),
            13 => Log::info('DISABLED env Bay', channel:'event'),
            14 => Log::info('DISABLED env Coast', channel:'event'),
            15 => Log::info('DISABLED env Island', channel:'event'),
            16 => Log::info('DISABLED env Rally', channel:'event'),
            17 => Log::info('DISABLED env Speed', channel:'event'),
            18 => $this->raspJukebox->chatY($player),
            19 => null, //ignored chatNo
            20 => $this->sendCommand($player, 'clearjukebox', false), //from admin panel?
            21 => $this->sendCommand($player, 'restartmap'),
            22 => $this->sendCommand($player, 'endround'),
            23 => $this->sendCommand($player, 'nextmap'),
            24 => $this->sendCommand($player, 'replaymap'),
            25 => $this->sendCommand($player, 'pass'),
            26 => $this->sendCommand($player, 'cancel'),
            27 => $this->sendCommand($player, 'players live'),
            28 => $this->donate->adminPay($login, true),
            29 => $this->donate->adminPay($login, false),
            30 => $this->donate->chatDonate($player, $this->donate->values[0]),
            31 => $this->donate->chatDonate($player, $this->donate->values[1]),
            32 => $this->donate->chatDonate($player, $this->donate->values[2]),
            33 => $this->donate->chatDonate($player, $this->donate->values[3]),
            34 => $this->donate->chatDonate($player, $this->donate->values[4]),
            35 => $this->donate->chatDonate($player, $this->donate->values[5]),
            36 => $this->donate->chatDonate($player, $this->donate->values[6]),
            default => null
        };

        $tot = max(0, count($player->get('page.data', [])) - 1);

        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->path}event_manialink",
            context: $this->buildData($player, $tot),
            formatMode: Sender::FORMAT_COLORS,
        );
    }

    public function displayPayment(string $login, string $serverName, string $label): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->path}payment",
            context: ['server' => $serverName, 'label' => $label],
            formatMode: Sender::FORMAT_COLORS
        );
    }

    public function mainWindowOff(string $login): void
    {
        $this->sender->sendXmlToLogin(login: $login, xml: '<manialink id="1"></manialink>');
    }

    public function allWindowsOff(): void
    {
        $this->sender->sendRenderToAll(template: "{$this->path}off", formatMode: Sender::FORMAT_NONE);
    }

    public function displayCpsPanel(string $login, int $cp, string $diff): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->path}cps_panel",
            context: ['cp' => $cp, 'diff' => $diff],
            formatMode: Sender::FORMAT_COLORS
        );
    }

    public function cpsPanelOff(string $login): void
    {
        $this->sender->sendXmlToLogin(login: $login, xml: '<manialink id="2"></manialink>');
    }

    public function allCpsPanelsOff(): void
    {
        $this->sender->sendXmlToAll(xml: '<manialink id="2"></manialink>');
    }

    public function displayAdmPanel(TmContainer $player): void
    {
        $this->sender->sendXmlToLogin(login: $player->get('Login'), xml: $player->get('panels.admin'));
    }

    public function admPanelOff(string $login): void
    {
        $this->sender->sendXmlToLogin(login: $login, xml: '<manialink id="3"></manialink>');
    }

    public function displayDonPanel(TmContainer $player, array $coppers): void
    {
        $xml = $player->get('panels.donate');
        for ($i = 1; $i <= 7; $i++) {
            $xml = str_replace('%COP' . $i . '%', $coppers[$i - 1], $xml);
        }

        $this->sender->sendXmlToLogin(login: $player->get('Login'), xml: $xml);
    }

    public function donPanelOff(string $login): void
    {
        $this->sender->sendXmlToLogin(login: $login, xml: '<manialink id="6"></manialink>');
    }

    public function displayRecPanel(TmContainer $player, $pb): void
    {
        $xml = str_replace(
            ['%PB%', '%TMX%', '%LCL%', '%DED%'],
            [$pb, $this->mlRecords['tmx'], $this->mlRecords['local'], $this->mlRecords['dedi']],
            $player->get('panels.records')
        );

        $this->sender->sendXmlToLogin(login: $player->get('Login'), xml: $xml);
    }

    public function recPanelOff(string $login): void
    {
        $this->sender->sendXmlToLogin(login: $login, xml: '<manialink id="4"></manialink>');
    }

    public function displayVotePanel(
        TmContainer $player,
        string $yes,
        string $no,
        int $timeout = 0
    ): void {
        $xml = str_replace(
            ['%YES%', '%NO%'],
            [$yes, $no],
            $player->get('panels.vote')
        );
        $this->sender->sendXmlToLogin(login: $player->get('Login'), xml: $xml, timeout: $timeout);
    }

    public function votePanelOff(string $login): void
    {
        $this->sender->sendXmlToLogin(login: $login, xml: '<manialink id="5"></manialink>');
    }

    public function allVotePanelsOff(): void
    {
        $this->sender->sendXmlToAll(xml: '<manialink id="5"></manialink>');
    }

    public function displayMsgWindow(array $msgs, int $timeout = 0): void
    {
        $validated = array_map([Aseco::class, 'validateUTF8'], $msgs);

        $this->sender->sendRenderToAll(
            template: "{$this->path}msgwindow",
            context: ['msgs' => $validated],
            timeout: $timeout
        );
    }

    public function msgLogButton(string $login): void
    {
        $this->sender->sendRenderToLogin(
            login: $login,
            template: "{$this->path}log_button",
            formatMode: Sender::FORMAT_COLORS
        );
    }

    //display_statspanel |statspanels_off -> disabled

    public function scorePanelOff(bool $panel = true): void
    {
        if (!$panel) {
            $this->sender->sendRenderToAll(
                template: "{$this->path}costum_ui",
                context: ['ui' => '<scoretable visible="False"/>']
            );
        }
    }

    public function scorePanelOn(): void
    {
        $this->sender->sendRenderToAll(
            template: "{$this->path}costum_ui",
            context: ['ui' => '<scoretable visible="True"/>'],
            formatMode: Sender::FORMAT_NONE
        );
    }

    public function roundsPanelOff(bool $panel = true): void
    {
        if (!$panel) {
            $this->sender->sendRenderToAll(
                template: "{$this->path}costum_ui",
                context: ['ui' => '<round_scores visible="False"/>'],
                formatMode: Sender::FORMAT_NONE
            );
        }
    }

    public function roundsPanelOn(TmContainer $player): void
    {
        $this->sender->sendRenderToLogin(
            login: $player->get('Login'),
            template: "{$this->path}costum_ui",
            context: ['ui' => '<round_scores visible="True"/>'],
            formatMode: Sender::FORMAT_COLORS
        );
    }

    private function sendCommand(
        TmContainer $player,
        string $command,
        bool $window = true
    ): void {
        if (!$window) {
            $this->mainWindowOff($player->get('Login'));
        }

        $parts = explode(' ', $command, 2);
        $params = array_values(Arr::except($parts, [0]));
        $player->set('command.name', $parts[0])->set('command.params', $params);

        $this->chatAdmin->handleChatCommand($player);
    }

    private function mainWindowAndJukebox(string $login): void
    {
        $this->mainWindowOff($login);
        $this->raspJukebox->chatList($login);
    }

    private function buildData(TmContainer $player, int $tot): array
    {
        $index   = $player->get('page.index');
        $pages   = $player->get('page.data');
        $config  = $player->get('page.config');
        $style   = $player->get('style');

        // current page lines (or empty if missing)
        $lines = $pages[$index] ?? [];
        $linesCount = count($lines);

        // make sure multipage windows have consistent height
        if ($tot > 1) {
            $linesCount = max($linesCount, count($pages[1] ?? []));
        }

        return [
            'id'         => 1,
            'ptr'        => $index,
            'tot'        => $tot,
            'header'     => $config['header'],
            'widths'     => $config['widths'],
            'icon'       => $config['icon'],
            'lines'      => $lines,
            'linesCount' => $linesCount,
            'hsize'      => $style->get('header.textsize'),
            'bsize'      => $style->get('body.textsize'),
            'style'      => $style,
            'hasPrev'    => $index > 0,
            'hasNext'    => $index < $tot,
            'add5'       => $tot > 5,
        ];
    }
}
