<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Core\Contracts\ContainerInterface;

/**
 * Trait ReadonlyProxyTrait
 *
 * Provides a way to create a deep-copied immutable proxy of a Container,
 * ensuring no mutations can occur on the proxy instance.
 */
trait ReadonlyProxyTrait
{
    /**
     * Returns an immutable proxy of the current container.
     * All mutating operations (set, unset, merge, clone, etc.) will throw LogicException.
     *
     * @return static
     */
    public function asReadonlyProxy(): static
    {
        return new class ($this->deepCopy($this)) extends Container {
            public function __construct(Container $copy)
            {
                foreach ($copy as $key => $value) {
                    $this[$key] = $value;
                }
                parent::setReadonly(true);
            }

            public function set(string $path, mixed $value): static
            {
                throw new \LogicException("Immutable proxy");
            }

            public function offsetSet(mixed $key, mixed $value): void
            {
                throw new \LogicException("Immutable proxy");
            }

            public function offsetUnset(mixed $key): void
            {
                throw new \LogicException("Immutable proxy");
            }

            public function delete(string $path): static
            {
                throw new \LogicException("Immutable proxy");
            }

            public function merge(ContainerInterface $other): static
            {
                throw new \LogicException("Immutable proxy");
            }

            public function setReadonly(bool $readonly = true): static
            {
                return $this;
            }

            public function __clone()
            {
                throw new \LogicException("Cloning of readonly container is not allowed.");
            }
        };
    }

    /**
     * Deep-copies a Container recursively, including all nested containers.
     *
     * @param ContainerInterface $container The container to copy.
     * @return static A deep copy of the given container.
     */
    public function deepCopy(ContainerInterface $container): static
    {
        $copy = new static();

        foreach ($container as $key => $value) {
            $copy[$key] = ($value instanceof static)
                ? $this->deepCopy($value)
                : $value;
        }

        return $copy;
    }
}
