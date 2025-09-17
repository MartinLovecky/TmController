<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

/**
 * Trait ReadonlyTrait
 *
 * Provides a mechanism to make an object and its nested structure immutable.
 * Useful for protecting internal state or ensuring config integrity after parsing.
 */
trait ReadonlyTrait
{
    /**
     * Indicates whether this object is currently in read-only mode.
     *
     * @var bool
     */
    private bool $readonly = false;

    /**
     * Locks the object (and its children) as read-only or unlocks it if false.
     *
     * @param bool $readonly Whether to set as read-only (true) or writable (false).
     * @return static Returns self, for fluent chaining.
     */
    public function setReadonly(bool $readonly = true): static
    {
        return $this->lockRecursive($readonly);
    }

    /**
     * Indicates whether this object is currently in read-only mode.
     *
     * @var bool
     */
    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * Internal method to recursively lock or unlock this object and any nested objects.
     *
     * @param bool $readonly Whether to lock (true) or unlock (false) the tree.
     * @return static Returns self for fluent use.
     */
    private function lockRecursive(bool $readonly = true): static
    {
        $this->readonly = $readonly;

        foreach ($this as $value) {
            if ($value instanceof self) {
                $value->lockRecursive($readonly);
            }
        }

        return $this;
    }

    /**
     * Throws a LogicException if the object is read-only.
     * Intended to be called before any mutation method like set(), unset(), etc.
     *
     * @throws \LogicException If the container is in read-only mode.
     */
    protected function assertWritable(): void
    {
        if ($this->isReadonly()) {
            throw new \LogicException("Cannot modify readonly Container");
        }
    }
}
