<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Yuhzel\TmController\App\Service\Server;

class WidgetBuilder
{
    private Environment $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader(Server::$twigDir);
        $this->twig = new Environment($loader, [
            'cache' => Server::$publicDir . 'cache',
            'auto_reload' => true,
            'debug' => true,            //(false in production)
            'strict_variables' => true
        ]);

        $this->twig->addFilter(new \Twig\TwigFilter('sum', function ($array) {
            return array_sum($array);
        }));

        if ($this->twig->isDebug()) {
            $this->twig->addExtension(new DebugExtension());
        }
    }

    /**
     * template extension is .xml.twig you dont need to provide it
     *
     * @param string $template
     * @param array $context
     * @return string
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template . '.xml.twig', $context);
    }
}
