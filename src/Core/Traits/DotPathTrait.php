<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

use Yuhzel\TmController\Core\Container;

trait DotPathTrait
{
    protected function navigateToParent(string $path, bool $createMissing): array
    {
        $segments = explode('.', $path);
        $lastKey = array_pop($segments);
        $current = $this;

        foreach ($segments as $segment) {
            if (!$current->offsetExists($segment) || !($current[$segment] instanceof self)) {
                if ($createMissing) {
                    $current[$segment] = new Container();
                } else {
                    return [null, $lastKey];
                }
            }

            $current = $current[$segment];
        }

        return [$current, $lastKey];
    }
}
