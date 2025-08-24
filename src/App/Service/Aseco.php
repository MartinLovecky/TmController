<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Services\Server;

class Aseco
{
    public static TmContainer $adminOps;
    public static int $restarting = 0;
    public static bool $startupPhase = false;
    public static array $colors = [
        'Welcome ' => '$f00',
        'Server ' => '$ff0',
        'Highlite ' => '$fff',
        'Timelite ' => '$bbb',
        'Record ' => '$0f3',
        'Emotic ' => '$fa0',
        'Music ' => '$d80',
        'Message ' => '$39f',
        'Rank ' => '$ff3',
        'Vote ' => '$f8f',
        'karma ' => '$ff0',
        'Donate ' => '$f0f',
        'Admin ' => '$ff0',
        'Black ' => '$000',
        'Grey ' => '$888',
        'Login ' => '$00f',
        'Logina ' => '$0c0',
        'Nick ' => '$f00',
        'interact ' => '$ff0$i',
        'dedimsg ' => '$28b',
        'dedirec ' => '$0b3',
        'Error ' => '$f00$i'
    ];

    /**
     * Its 2025 just stop nonsence - Yuha
     *
     * @param string $input
     * @param string $invalidRepl
     * @return string
     */
    public static function validateUTF8(string $input, $invalidRepl = '?'): string
    {
        $clean = iconv('UTF-8', 'UTF-8//IGNORE', $input);
        return ($clean !== false && $clean !== '') ? $clean : $invalidRepl;
    }


    public static function isStartup(): bool
    {
        return self::$startupPhase;
    }

    public static function isAnyAdmin(string $login): bool
    {
        $admin = self::$adminOps->get('admins')->toArray();
        $ops = self::$adminOps->get('operators')->toArray();

        return in_array($login, array_unique(array_merge($admin, $ops)));
    }

    public static function updateEnvFile(string $key, string $value): void
    {
        $envPath = Server::$rootDir . '.env';

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $found = false;

        foreach ($lines as &$line) {
            if (str_starts_with(trim($line), "$key=")) {
                $line = "$key=\"$value\"";
                $found = true;
                break;
            }
        }

        if (!$found) {
            $lines[] = "$key=\"$value\"";
        }

        file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL);

        $_ENV[$key] = $value;
    }

    public static function formatText(mixed ...$args): string
    {
        $text = (string)array_shift($args);

        foreach ($args as $i => $param) {
            $text = str_replace('{' . ($i + 1) . '}', (string)$param, $text);
        }

        return $text;
    }

    public static function formatColors(string $text): string
    {
        foreach (self::$colors as $color => $value) {
            $text = str_replace('{#' . strtolower($color) . '}', $value, $text);
        }

        return $text;
    }

    public static function formatTime(int $MwTime, bool $hsec = true): string
    {
        if ($MwTime === -1) {
            return '???';
        }

        // Calculate minutes, seconds, and hundredths of seconds
        $minutes = floor($MwTime / (1000 * 60));
        $seconds = floor(($MwTime % (1000 * 60)) / 1000);
        $hundredths = floor(($MwTime % 1000) / 10);

        // Format the time string based on whether hundredths of seconds are needed
        if ($hsec) {
            $formattedTime = sprintf('%02d:%02d.%02d', $minutes, $seconds, $hundredths);
        } else {
            $formattedTime = sprintf('%02d:%02d', $minutes, $seconds);
        }

        // Remove leading zero if present
        if ($formattedTime[0] == '0') {
            $formattedTime = ltrim($formattedTime, '0');
        }

        return $formattedTime;
    }

    public static function formatTimeH(int $MwTime, bool $hsec = true): string
    {
        if ($MwTime == -1) {
            return '???';
        }

        $totalSeconds = floor($MwTime / 1000);
        $hundredths = floor(($MwTime % 1000) / 10);

        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        if ($hsec) {
            return sprintf('%02d:%02d:%02d.%02d', $hours, $minutes, $seconds, $hundredths);
        } else {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }
    }

    public static function console(mixed ...$args): void
    {
        $formattedText = self::formatText(...$args);
        $timestamp = date('m/d,H:i:s');
        $message = "[{$timestamp}] {$formattedText}" . PHP_EOL;

        echo $message;
        flush();
    }

    public static function consoleText(mixed ...$args): void
    {
        $formattedText = self::formatText(...$args) . PHP_EOL;
        echo $formattedText;
        flush();
    }

    public static function mapCountry(string $country): string
    {
        $nations = array(
            'Afghanistan' => "AFG",
            "Albania" => "ALB",
            "Algeria" => "ALG",
            "Andorra" => "AND",
            "Angola" => "ANG",
            "Argentina" => "ARG",
            "Armenia" => "ARM",
            "Aruba" => "ARU",
            "Australia" => "AUS",
            "Austria" => "AUT",
            "Azerbaijan" => "AZE",
            "Bahamas" => "BAH",
            "Bahrain" => "BRN",
            "Bangladesh" => "BAN",
            "Barbados" => "BAR",
            "Belarus" => "BLR",
            "Belgium" => "BEL",
            "Belize" => "BIZ",
            "Benin" => "BEN",
            "Bermuda" => "BER",
            "Bhutan" => "BHU",
            "Bolivia" => "BOL",
            "Bosnia&Herzegovina" => "BIH",
            "Botswana" => "BOT",
            "Brazil" => "BRA",
            "Brunei" => "BRU",
            "Bulgaria" => "BUL",
            "Burkina Faso" => "BUR",
            "Burundi" => "BDI",
            "Cambodia" => "CAM",
            "Cameroon" => "CMR",
            "Canada" => "CAN",
            "Cape Verde" => "CPV",
            "Central African Republic" => "CAF",
            "Chad" => "CHA",
            "Chile" => "CHI",
            "China" => "CHN",
            "Chinese Taipei" => "TPE",
            "Colombia" => "COL",
            "Congo" => "CGO",
            "Costa Rica" => "CRC",
            "Croatia" => "CRO",
            "Cuba" => "CUB",
            "Cyprus" => "CYP",
            "Czech Republic" => "CZE",
            "Czech republic" => "CZE",
            "DR Congo" => "COD",
            "Denmark" => "DEN",
            "Djibouti" => "DJI",
            "Dominica" => "DMA",
            "Dominican Republic" => "DOM",
            "Ecuador" => "ECU",
            "Egypt" => "EGY",
            "El Salvador" => "ESA",
            "Eritrea" => "ERI",
            "Estonia" => "EST",
            "Ethiopia" => "ETH",
            "Fiji" => "FIJ",
            "Finland" => "FIN",
            "France" => "FRA",
            "Gabon" => "GAB",
            "Gambia" => "GAM",
            "Georgia" => "GEO",
            "Germany" => "GER",
            "Ghana" => "GHA",
            "Greece" => "GRE",
            "Grenada" => "GRN",
            "Guam" => "GUM",
            "Guatemala" => "GUA",
            "Guinea" => "GUI",
            "Guinea-Bissau" => "GBS",
            "Guyana" => "GUY",
            "Haiti" => "HAI",
            "Honduras" => "HON",
            "Hong Kong" => "HKG",
            "Hungary" => "HUN",
            "Iceland" => "ISL",
            "India" => "IND",
            "Indonesia" => "INA",
            "Iran" => "IRI",
            "Iraq" => "IRQ",
            "Ireland" => "IRL",
            "Israel" => "ISR",
            "Italy" => "ITA",
            "Ivory Coast" => "CIV",
            "Jamaica" => "JAM",
            "Japan" => "JPN",
            "Jordan" => "JOR",
            "Kazakhstan" => "KAZ",
            "Kenya" => "KEN",
            "Kiribati" => "KIR",
            "Korea" => "KOR",
            "Kuwait" => "KUW",
            "Kyrgyzstan" => "KGZ",
            "Laos" => "LAO",
            "Latvia" => "LAT",
            "Lebanon" => "LIB",
            "Lesotho" => "LES",
            "Liberia" => "LBR",
            "Libya" => "LBA",
            "Liechtenstein" => "LIE",
            "Lithuania" => "LTU",
            "Luxembourg" => "LUX",
            "Macedonia" => "MKD",
            "Malawi" => "MAW",
            "Malaysia" => "MAS",
            "Mali" => "MLI",
            "Malta" => "MLT",
            "Mauritania" => "MTN",
            "Mauritius" => "MRI",
            "Mexico" => "MEX",
            "Moldova" => "MDA",
            "Monaco" => "MON",
            "Mongolia" => "MGL",
            "Montenegro" => "MNE",
            "Morocco" => "MAR",
            "Mozambique" => "MOZ",
            "Myanmar" => "MYA",
            "Namibia" => "NAM",
            "Nauru" => "NRU",
            "Nepal" => "NEP",
            "Netherlands" => "NED",
            "New Zealand" => "NZL",
            "Nicaragua" => "NCA",
            "Niger" => "NIG",
            "Nigeria" => "NGR",
            "Norway" => "NOR",
            "Oman" => "OMA",
            "Other Countries" => "OTH",
            "Pakistan" => "PAK",
            "Palau" => "PLW",
            "Palestine" => "PLE",
            "Panama" => "PAN",
            "Paraguay" => "PAR",
            "Peru" => "PER",
            "Philippines" => "PHI",
            "Poland" => "POL",
            "Portugal" => "POR",
            "Puerto Rico" => "PUR",
            "Qatar" => "QAT",
            "Romania" => "ROU",
            "Russia" => "RUS",
            "Rwanda" => "RWA",
            "Samoa" => "SAM",
            "San Marino" => "SMR",
            "Saudi Arabia" => "KSA",
            "Senegal" => "SEN",
            "Serbia" => "SRB",
            "Sierra Leone" => "SLE",
            "Singapore" => "SIN",
            "Slovakia" => "SVK",
            "Slovenia" => "SLO",
            "Somalia" => "SOM",
            "South Africa" => "RSA",
            "Spain" => "ESP",
            "Sri Lanka" => "SRI",
            "Sudan" => "SUD",
            "Suriname" => "SUR",
            "Swaziland" => "SWZ",
            "Sweden" => "SWE",
            "Switzerland" => "SUI",
            "Syria" => "SYR",
            "Taiwan" => "TWN",
            "Tajikistan" => "TJK",
            "Tanzania" => "TAN",
            "Thailand" => "THA",
            "Togo" => "TOG",
            "Tonga" => "TGA",
            "Trinidad and Tobago" => "TRI",
            "Tunisia" => "TUN",
            "Turkey" => "TUR",
            "Turkmenistan" => "TKM",
            "Tuvalu" => "TUV",
            "Uganda" => "UGA",
            "Ukraine" => "UKR",
            "United Arab Emirates" => "UAE",
            "United Kingdom" => "GBR",
            "United States of America" => "USA",
            "Uruguay" => "URU",
            "Uzbekistan" => "UZB",
            "Vanuatu" => "VAN",
            "Venezuela" => "VEN",
            "Vietnam" => "VIE",
            "Yemen" => "YEM",
            "Zambia" => "ZAM",
            "Zimbabwe" => "ZIM",
        );

        $parts = explode('|', $country);

        if (!isset($parts[1]) || trim($parts[1]) === '') {
            return 'OTH';
        }

        $countryPart = trim($parts[1]);

        if (!array_key_exists($countryPart, $nations)) {
            return 'OTH';
        }

        return $nations[$countryPart];
    }

    public static function stripColors(string $input, bool $for_tm = true): string
    {
        // Replace all occurrences of double dollar signs with a null character
        $input = str_replace('$$', "\0", $input);

        // Strip TMF H, L, & P links, keeping the first and second capture groups if present
        $input = preg_replace(
            '/
            # Match and strip H, L, and P links with square brackets
            \$[hlp]         # Match a $ followed by h, l, or p (link markers)
            (.*?)           # Non-greedy capture of any content after the link marker
            (?:             # Start non-capturing group for possible brackets content
                \[.*?\]     # Match any content inside square brackets
                (.*?)       # Non-greedy capture of any content after the square brackets
            )*              # Zero or more occurrences of the bracketed content
            (?:\$[hlp]|$)   # Match another $ with h, l, p or end of string
            /ixu',
            '$1$2',  // Replace with the content of the first and second capture groups
            $input
        );

        // Strip various patterns beginning with an unescaped dollar sign
        $input = preg_replace(
            '/
            # Match a single unescaped dollar sign and one of the following:
            \$
            (?:
                [0-9a-f][^$][^$]  # Match color codes: hexadecimal + 2 more chars
                | [0-9a-f][^$]    # Match incomplete color codes
                | [^][hlp]        # Match any style code that isn’t H, L, or P
                | (?=[][])        # Match $ followed by [ or ], but keep the brackets
                | $               # Match $ at the end of the string
            )
            /ixu',
            '',  // Remove the dollar sign and matched sequence
            $input
        );

        // Restore null characters to dollar signs if needed for displaying in TM or logs
        return str_replace("\0", $for_tm ? '$$' : '$', $input);
    }

    public static function getChatMessage(string $message, string $jsonFile = 'messages'): ?string
    {
        $file = self::safeFileGetContents(Server::$jsonDir . "{$jsonFile}.json");

        if (!$file) {
            return null;
        }

        $arr = self::safeJsonDecode($file);

        if (empty($arr)) {
            return null;
        }

        if (array_key_exists($message, $arr)) {
            return htmlspecialchars_decode($arr[$message]);
        }

        self::console('[X8seco] Invalid message in getChatMessage [{1}]', $message);
        return null;
    }

    public static function safeFileGetContents(string $filename): ?string
    {
        if (!is_readable($filename) && !is_file($filename)) {
            return null;
        }

        $content = @file_get_contents($filename);

        return $content !== false ? $content : null;
    }

    public static function safeJsonDecode(string $json, bool $assoc = true): ?array
    {
        // Reset previous error state
        json_last_error();

        $decoded = json_decode($json, $assoc);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::console('[X8seco] JSON decode error: ' . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    public static function isBase64(string $str): bool
    {
        if (!preg_match('/^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/', $str)) {
            return false;
        }

        $decoded = base64_decode($str, true);

        return $decoded !== false && base64_encode($decoded) === $str;
    }
}
