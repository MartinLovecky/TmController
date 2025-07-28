<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Yuhzel\TmController\App\Aseco;

class WidgetBuilder
{
    private Environment $twig;

    public function __construct()
    {
        $loader = new FilesystemLoader(Aseco::path() . 'public' . DIRECTORY_SEPARATOR . 'templates');
        $this->twig = new Environment($loader, [
            'cache' => Aseco::path() . 'public' . DIRECTORY_SEPARATOR . 'cache',
            'auto_reload' => true,
            'debug' => true,            //(false in production)
            'strict_variables' => true
        ]);

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
