<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Plugins;

use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Container;

class FufiMenu
{
    private Container $config;
    private array $styles = [];
    public function __construct()
    {
        $this->config = Container::fromJsonFile(Aseco::jsonFolderPath() . 'fufiConfig.json');
        $this->loadSettings();
        $this->loadStyles();
    }

    private function loadSettings(): void
    {
        $filePath = Aseco::path()
            . 'public'
            . DIRECTORY_SEPARATOR
            . 'xml'
            . DIRECTORY_SEPARATOR
            . 'fufi_menu.xml';
        $xmlBlocks = Aseco::safeFileGetContents($filePath);

        if ($xmlBlocks) {
            $this->config->set('settings.blocks', $this->getXMLTemplateBlocks($xmlBlocks));
        }
    }

    private function getXMLTemplateBlocks(string $xml): array
    {
        $result = [];
        $xmlCopy = $xml;

        while (str_contains($xmlCopy, '<!--start_')) {
            $startPos = strpos($xmlCopy, '<!--start_') + 10;
            $xmlCopy = substr($xmlCopy, $startPos);

            $endPos = strpos($xmlCopy, '-->');
            $title = substr($xmlCopy, 0, $endPos);

            $result[$title] = trim($this->getXMLBlock($xml, $title));

            // Move past the end comment
            $xmlCopy = substr($xmlCopy, $endPos + 3);
        }

        return $result;
    }

    private function getXMLBlock($haystack, $caption): string
    {
        $startStr = "<!--start_$caption-->";
        $endStr = "<!--end_$caption-->";

        $startPos = strpos($haystack, $startStr);
        if ($startPos === false) {
            return ''; // Handle error or unexpected state
        }

        $startPos += strlen($startStr);
        $endPos = strpos($haystack, $endStr, $startPos);
        if ($endPos === false) {
            return ''; // Handle error or unexpected state
        }

        return substr($haystack, $startPos, $endPos - $startPos);
    }

    //NOTE: this is not necessary now we map a to b
    // a) $this->config->get("settings.styles.menubutton._style"); to
    // b) $this->style[menubutton][style];
    private function loadStyles(): void
    {
        $elements = [
            'menubutton',
            'menubackground',
            'menuentry',
            'menuentryactive',
            'menugroupicon',
            'menuicon',
            'menuactionicon',
            'menuhelpicon',
            'separator',
            'indicatorfalse',
            'indicatortrue',
            'indicatoronhold'
        ];

        foreach ($elements as $element) {
            $this->styles[$element]['style'] = $this->config->get("settings.styles.{$element}._style");
            $this->styles[$element]['substyle'] = $this->config->get("settings.styles.{$element}._substyle");
        }
    }
}
