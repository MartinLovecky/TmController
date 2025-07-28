<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

trait ReadonlyTrait
{
    private bool $readonly = false;

    public function setReadonly(bool $readonly = true): static
    {
        return $this->lockRecursive($readonly);
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    protected function assertWritable(): void
    {
        if ($this->isReadonly()) {
            throw new \LogicException("Cannot modify readonly Container");
        }
    }

    private function lockRecursive(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        $traverse = function ($value) use ($readonly, &$traverse) {
            if ($value instanceof self) {
                $value->readonly = $readonly;
                foreach ($value as $innerValue) {
                    $traverse($innerValue);
                }
            } elseif (is_iterable($value)) {
                foreach ($value as $item) {
                    $traverse($item);
                }
            }
        };

        foreach ($this as $value) {
            $traverse($value);
        }

        return $this;
    }
}
