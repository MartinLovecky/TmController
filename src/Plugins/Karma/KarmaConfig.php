<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins\Karma;

class KarmaConfig
{
    private array $widgets = [];
    private array $reminderWindow = [];
    private array $messages = [];
    private array $images = [];
    private array $urls = [];
    private array $timeouts = [];
    private array $settings = [];

    public function __construct()
    {
        $this->widgets = [
            'rounds' => [
                'pos_x' => 49.2,
                'pos_y' => 32.86,
                'title' => null,
                'icon_style' => null,
                'icon_substyle' => null,
                'background_style' => null,
                'background_substyle' => null,
            ],
            'time_attack' => [
                'pos_x' => 49.2,
                'pos_y' => 32.86,
                'title' => '$FFFManiaKarma',
                'icon_style' => 'Icons64x64_1',
                'icon_substyle' => 'ToolLeague1',
                'background_style' => 'Bgs1InRace',
                'background_substyle' => 'NavButton',
                'title_style'   => 'BgsPlayerCard',
                'title_substyle' => 'BgRacePlayerName'
            ],
            'team' => [
                'pos_x' => 49.2,
                'pos_y' => 32.86,
                'title' => null,
                'icon_style' => null,
                'icon_substyle' => null,
                'background_style' => null,
                'background_substyle' => null,
            ],
            'laps' => [
                'pos_x' => 49.2,
                'pos_y' => 27.36,
                'title' => null,
                'icon_style' => null,
                'icon_substyle' => null,
                'background_style' => null,
                'background_substyle' => null,
            ],
            'stunts' => [
                'pos_x' => 49.2,
                'pos_y' => 17.5,
                'title' => null,
                'icon_style' => null,
                'icon_substyle' => null,
                'background_style' => null,
                'background_substyle' => null,
            ],
            'cup' => [
                'pos_x' => 49.2,
                'pos_y' => 32.86,
                'title' => null,
                'icon_style' => null,
                'icon_substyle' => null,
                'background_style' => null,
                'background_substyle' => null,
            ],
            'score' => [
                'pos_x' => 47.8,
                'pos_y' => 33.2,
                'title' => '$FFFManiaKarma',
                'icon_style' => 'Icons64x64_1',
                'icon_substyle' => 'ToolLeague1',
                'background_style' => 'BgsPlayerCard',
                'background_substyle' => 'BgRacePlayerName',
                'title_style'   => 'BgsPlayerCard',
                'title_substyle' => 'ProgressBar'
            ],
        ];

        $this->reminderWindow = [
            'display' => 'score',
            'race' => ['pos_x' => -41, 'pos_y' => -29.35],
            'score' => ['pos_x' => -41, 'pos_y' => -26],
        ];

        $this->messages = [
            'welcome' => '$ff0*** This Server uses the ManiaKarma world Track vote system at '
                . '$fff$l[{1}]{2}$l' . "\n"
                . '$ff0*** Type $fff/karma help$ff0 for more info.',
            'uptodate_ok' => '$fa0*** This ManiaKarma plugin version $fff{1}$fa0 is up to date.',
            'uptodate_new' => '$fa0*** New ManiaKarma plugin version $fff{1}$fa0 available from $fff{2}',
            'uptodate_failed' => '$f00$i*** Could not check plugin version, connection failed.',
            'karma_message' => '$ff0> $ff0Karma of $fff{1}$ff0 is $fff{2}',
            'karma_your_vote' => '$ff0, you think this Track is $fff{1}$ff0. Command $fff{2}',
            'karma_not_voted' => '$ff0, you have $fffnot yet voted!',
            'karma_details' => '$ff0> $ff0Fantastic: $fff{2}%$ff0 ($n$fff{3}$ff0$m), '
                . 'Beautiful: $fff{4}%$ff0 ($n$fff{5}$ff0$m), '
                . 'Good: $fff{6}%$ff0 ($n$fff{7}$ff0$m), '
                . 'Bad: $fff{8}%$ff0 ($n$fff{9}$ff0$m), '
                . 'Poor: $fff{10}%$ff0 ($n$fff{11}$ff0$m), '
                . 'Waste: $fff{12}%$ff0 ($n$fff{13}$ff0$m)',
            'karma_done' => '$ff0> $ff0Vote successful for Track $fff{1}$ff0!',
            'karma_change' => '$ff0> $ff0Vote changed for Track $fff{1}$ff0!',
            'karma_voted' => '$ff0> $ff0You have already voted for this Track',
            'karma_remind' => '$ff0> $ff0Remember to vote karma for this Track! $fff/karma help$ff0 for more info.',
            'karma_require_finish' => '$ff0> $f00$iYou need to finish this Track at least $fff$I {1}$f00$i time{2}',
            'karma_no_public' => '$ff0> $f00$iPublic karma votes disabled, use $i$fff{1}$f00$i instead!',
            'karma_list_help' => '$ff0> $fa0Use $fff/list$fa0 first - Type $fff/list help$fa0 for more info.',
            'karma_help' => '$ff0This is the World Track vote System, all votes are stored in a central Database '
                . 'and not only at this Server. The Karma of each Track $ff0are from all votes Worldwide.' . "\n"
                . 'The Authors and the Admins need to know how good the Tracks are, so please vote all Tracks!' . "\n"
                . '$ff0You can vote with:' . "\n"
                . '  $fff/+++$ff0 for fantastic' . "\n"
                . '  $fff/++$ff0   for beautiful' . "\n"
                . '  $fff/+$ff0     for good' . "\n"
                . '  $fff/-$ff0      for bad' . "\n"
                . '  $fff/--$ff0     for poor' . "\n"
                . '  $fff/---$ff0    for waste' . "\n"
                . '$ff0You can also use $fffKarma-Widget$ff0 or the $fffReminder-Window$ff0 at the Scoretable.' . "\n"
                . '$ff0Enter $fff/karma$ff0 to see the Karma of the current Track.' . "\n"
                . '$ff0Enter $fff/karma details$ff0 to see the detailed Karma of the current Track.',
            'karma_reminder_at_score' => 'How did you like this Track?',
            'karma_vote_singular' => 'vote',
            'karma_vote_plural' => 'votes',
            'karma_you_have_voted' => 'You have voted:',
            'karma_fantastic' => 'fantastic',
            'karma_beautiful' => 'beautiful',
            'karma_good' => 'good',
            'karma_undecided' => 'undecided',
            'karma_bad' => 'bad',
            'karma_poor' => 'poor',
            'karma_waste' => 'waste',
            'karma_show_opinion' => '$ff0>> $fff[{1}]$ff0 thinks this Track is $fff{2}$ff0! What do you think?',
            'karma_show_undecided' => '$ff0>> $fff[{1}]$ff0 is $fffundecided$ff0 about this Track. What do you think?',
            'lottery_mail_body' => "\n"
                . '$ff0Congratulations! You have won at the ManiaKarma lottery on $fff{1}$ff0!' . "\n"
                . 'You won $fff{2} Coppers$ff0!!!' . "\n"
                . '$L[tmtp://#addfavourite={3}]Click here to add this server to your favorites!$L',
            'lottery_player_won' => '$ff0> {1}$Z$S$ff0 won the lottery and got $fff{2} Coppers$ff0!!! Congratulations!',
            'lottery_low_coppers' => '$ff0> $39fThe lottery has too few Coppers, please donate some to enable lottery.',
            'lottery_to_few_players' => '$ff0> $39fThe lottery did not draw a winner, not enough players online '
                . 'or too few voted.',
            'lottery_total_player_win' => '$ff0> $ff0You have won a total of $fff{1} Coppers$ff0 until now.',
            'lottery_help' => "\n"
                . '$ff0Enter $fff/karma lottery$ff0 to see your current total win at ManiaKarma lottery '
                . '(lottery only available for TMU players).',
        ];

        $this->images = [
            'widget_open_left' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/edge-open-ld-dark.png',
            'widget_open_right' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/edge-open-rd-dark.png',
            'tmx_logo_normal' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/logo-tmx-normal.png',
            'tmx_logo_focus' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/logo-tmx-focus.png',
            'cup_gold' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/cup_gold.png',
            'cup_silver' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/cup_silver.png',
            'maniakarma_logo' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/logo-tm-karma.png',
            'progress_indicator' => 'http://maniacdn.net/undef.de/xaseco1/mania-karma/progress_bar_yellow_2D.bik'
        ];

        $this->urls = [
            'website' => 'www.mania-karma.com',
            'api_auth' => 'http://worldwide.mania-karma.com/api/tmforever-trackmania-v4.php',
        ];

        $this->timeouts = [
            'wait_timeout' => 40,
            'connect_timeout' => 30,
            'keepalive_min_timeout' => 100,
        ];

        $this->settings = [
            'number_format' => 'english',
            'show_welcome' => true,
            'allow_public_vote' => true,
            'show_at_start' => true,
            'show_details' => true,
            'show_votes' => true,
            'show_karma' => true,
            'require_finish' => 1,
            'remind_to_vote' => 'score',
            'score_mx_window' => true,
        ];
    }

    // --- Getters ---
    public function getWidget(string $gamemode): ?array
    {
        return $this->widgets[$gamemode] ?? null;
    }

    public function getReminderWindow(string $gamemode): ?array
    {
        return $this->reminderWindow[$gamemode] ?? null;
    }

    public function getMessage(string $key): ?string
    {
        return $this->messages[$key] ?? null;
    }

    public function getImage(string $key): ?string
    {
        return $this->images[$key] ?? null;
    }

    public function getUrl(string $key): ?string
    {
        return $this->urls[$key] ?? null;
    }

    public function getTimeout(string $key): ?int
    {
        return $this->timeouts[$key] ?? null;
    }

    public function getSetting(string $key): mixed
    {
        return $this->settings[$key] ?? null;
    }
}
