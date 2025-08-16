<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

use Yuhzel\TmController\Core\Container;

trait DotPathTrait
{
    /**
     * Navigate to the parent container of the last segment in a dot-path.
     *
     * Supports escaped dots (\.) in keys and arrays
     * Can create missing intermediate containers if $createMissing is true.
     *
     * @param string $path Dot-separated path
     * @param bool $createMissing Whether to create missing intermediate containers
     * @return array{Container|null, string|null} [$parentContainer, $lastKey]
     */
    protected function navigateToParent(string $path, bool $createMissing): array
    {
        if ($path === '') {
            return [$this, null];
        }

        $segments = preg_split('/(?<!\\\\)\./', $path);
        $segments = array_map(fn($seg) => str_replace('\.', '.', $seg), $segments);
        $lastKey = array_pop($segments);
        $current = $this;

        foreach ($segments as $segment) {
            if (ctype_digit((string)$segment)) {
                $segment = (int)$segment;
            }

            if (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    if ($createMissing) {
                        $current[$segment] = [];
                    } else {
                        return [null, $lastKey];
                    }
                }
            } elseif ($current instanceof self) {
                if (!$current->offsetExists($segment)) {
                    if ($createMissing) {
                        $current[$segment] = new self();
                    } else {
                        return [null, $lastKey];
                    }
                }
            } else {
                return [null, $lastKey];
            }

            $current = is_array($current) ? $current[$segment] : $current[$segment];
        }

        return [$current, $lastKey];
    }
}
