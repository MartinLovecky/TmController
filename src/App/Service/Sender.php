<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

use Yuhzel\TmController\Core\TmContainer;
use Yuhzel\TmController\Infrastructure\Gbx\Client;

class Sender
{
    public const FORMAT_NONE   = 'none';
    public const FORMAT_COLORS = 'colors';
    public const FORMAT_TEXT   = 'text';
    public const FORMAT_BOTH   = 'both';

    public function __construct(
        private Client $client,
        private WidgetBuilder $widgetBuilder
    ) {
    }

    /**
     * Generic query wrapper with optional formatting
     *
     * @param string $method
     * @param array $params
     * @param array $formatArgs
     * @param string $formatMode
     * @return TmContainer
     */
    public function query(
        string $method,
        array $params = [],
        array $formatArgs = [],
        string $formatMode = self::FORMAT_NONE
    ): TmContainer {
        foreach ($params as $i => $param) {
            if (is_string($param)) {
                $params[$i] = $this->format($param, $formatArgs, $formatMode);
            }
        }

        return $this->client->query($method, $params);
    }

    /**
     * Send raw XML to all players
     *
     * @param string $xml
     * @param array $formatArgs
     * @param string $formatMode
     * @param integer $timeout
     * @param boolean $hideOnEsc
     * @return void
     */
    public function sendXmlToAll(
        string $xml,
        array $formatArgs = [],
        string $formatMode = self::FORMAT_NONE,
        int $timeout = 0,
        bool $hideOnEsc = false
    ): void {
        $this->query('SendDisplayManialinkPage', [$xml, $timeout, $hideOnEsc], $formatArgs, $formatMode);
    }

    /**
     *  Send raw XML to a specific player by login
     *
     * @param string $login
     * @param string $xml
     * @param array $formatArgs
     * @param string $formatMode
     * @param integer $timeout
     * @param boolean $hideOnEsc
     * @return void
     */
    public function sendXmlToLogin(
        string $login,
        string $xml,
        array $formatArgs = [],
        string $formatMode = self::FORMAT_NONE,
        int $timeout = 0,
        bool $hideOnEsc = false
    ): void {
        $this->query('SendDisplayManialinkPageToLogin', [$login, $xml, $timeout, $hideOnEsc], $formatArgs, $formatMode);
    }

    /**
     * Render template and send to all players
     * TODO (yuha): formatting render is stupid 1st mod templates
     * @param string $template
     * @param array $context
     * @param array $formatArgs
     * @param string $formatMode
     * @param integer $timeout
     * @param boolean $hideOnEsc
     * @return void
     */
    public function sendRenderToAll(
        string $template,
        array $context = [],
        array $formatArgs = [],
        string $formatMode = self::FORMAT_NONE,
        int $timeout = 0,
        bool $hideOnEsc = false
    ): void {
        $xml = $this->widgetBuilder->render($template, $this->prepareContext($context, $formatMode));
        $this->sendXmlToAll($xml, $formatArgs, $formatMode, $timeout, $hideOnEsc);
    }

    /**
     * Render a Twig XML HUD template and send it to a specific player.
     * TODO (yuha): formatting render is stupid 1st mod templates
     * @param string  $login      to send the HUD to.
     * @param string  $template   The path or name to render (e.g. 'xml.twig').
     * @param array   $context    Key-value pairs passed for rendering.
     * @param array   $formatArgs Optional arguments for formatting placeholders in the XML.
     * @param string  $formatMode Defines how formatting is applied (see self::FORMAT_* constants).
     * @param int     $timeout    Time in milliseconds before the HUD disappears (0 = persistent).
     * @param bool    $hideOnEsc  Whether the HUD should close when the player presses ESC.
     *
     * @return void
     */
    public function sendRenderToLogin(
        string $login,
        string $template,
        array $context = [],
        array $formatArgs = [],
        string $formatMode = self::FORMAT_NONE,
        int $timeout = 0,
        bool $hideOnEsc = false
    ): void {
        $xml = $this->widgetBuilder->render($template, $this->prepareContext($context, $formatMode));
        $this->sendXmlToLogin($login, $xml, $formatArgs, $formatMode, $timeout, $hideOnEsc);
    }

    /**
     * Send chat message to all players
     *
     * @param string $message
     * @param array $formatArgs
     * @param string $formatMode
     * @return void
     */
    public function sendChatMessageToAll(
        string $message,
        array $formatArgs = [],
        string $formatMode = self::FORMAT_BOTH
    ): void {
        $this->query('ChatSendServerMessage', [$message], $formatArgs, $formatMode);
    }

    /**
     * Send chat message to a specific player
     *
     * @param string $login
     * @param string $message
     * @param array $formatArgs
     * @param string $formatMode
     * @return void
     */
    public function sendChatMessageToLogin(
        string $login,
        string $message,
        array $formatArgs = [],
        string $formatMode = self::FORMAT_BOTH
    ): void {
        $this->query('ChatSendServerMessageToLogin', [$message, $login], $formatArgs, $formatMode);
    }

    /**
     * Apply text and/or color formatting
     *
     * @param string $text
     * @param array $args
     * @param string $mode
     * @return string
     */
    private function format(
        string $text,
        array $args = [],
        string $mode = self::FORMAT_BOTH
    ): string {
        switch ($mode) {
            case self::FORMAT_COLORS:
                $text = Aseco::formatColors($text);
                break;
            case self::FORMAT_TEXT:
                if (!empty($args)) {
                    $text = Aseco::formatText($text, ...$args);
                }
                break;
            case self::FORMAT_BOTH:
                if (!empty($args)) {
                    $text = Aseco::formatText($text, ...$args);
                }
                $text = Aseco::formatColors($text);
                break;
            case self::FORMAT_NONE:
            default:
                break;
        }
        return $text;
    }

    /**
     * Apply formatting to template context
     *
     * @param array $context
     * @param string $mode
     * @return array
     */
    private function prepareContext(
        array $context,
        string $mode = self::FORMAT_BOTH
    ): array {
        foreach ($context as $key => $value) {
            if (is_string($value)) {
                $context[$key] = $this->format($value, [], $mode);
            }
        }
        return $context;
    }
}
