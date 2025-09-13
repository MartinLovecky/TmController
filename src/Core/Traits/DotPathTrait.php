<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

use Yuhzel\TmController\Core\TmContainer;

trait DotPathTrait
{
    /**
     * Navigate to the parent TmContainer of the last segment in a dot-path.
     *
     * Supports escaped dots (\.) in keys and arrays
     * Can create missing intermediate TmContainers if $createMissing is true.
     *
     * @param string $path Dot-separated path
     * @param bool $createMissing Whether to create missing intermediate TmContainers
     * @return array{TmContainer|null, string|null} [$parentTmContainer, $lastKey]
     */
    protected function navigateToParent(string $path, bool $createMissing): array
    {
        if ($path === '') {
            return [$this, null];
        }

        $segments = preg_split('/(?<!\\\\)\./', $path);
        $segments = array_map(fn ($seg) => str_replace('\.', '.', $seg), $segments);

        $lastKey = array_pop($segments);
        if (ctype_digit((string)$lastKey)) {
            $lastKey = (int)$lastKey;
        }

        $current = $this;

        foreach ($segments as $segment) {
            if (ctype_digit((string)$segment)) {
                $segment = (int)$segment;
            }

            if ($current instanceof self) {
                if (!$current->offsetExists($segment)) {
                    if ($createMissing) {
                        $current[$segment] = new self();
                    } else {
                        return [null, $lastKey];
                    }
                }
                $current = $current[$segment];
            } elseif (is_array($current)) {
                if (!array_key_exists($segment, $current)) {
                    if ($createMissing) {
                        $current[$segment] = [];
                    } else {
                        return [null, $lastKey];
                    }
                }
                $current = $current[$segment];
            } else {
                return [null, $lastKey];
            }
        }

        return [$current, $lastKey];
    }
}
