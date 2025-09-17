<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core;

use ArrayObject;
use Yuhzel\TmController\Core\Contracts\ContainerInterface;
use Yuhzel\TmController\Core\Traits\{DotPathTrait, ReadonlyProxyTrait, ReadonlyTrait};

/**
 * A recursive, dot-path-accessible container with support for readonly state and deep merging.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class Container extends ArrayObject implements ContainerInterface
{
    use DotPathTrait;
    use ReadonlyTrait;
    use ReadonlyProxyTrait;

    public function __construct()
    {
        parent::__construct([], ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Set a value at the dotted path. Creates intermediate nodes if missing.
     *
     * @param string $path
     * @param mixed $value
     * @return $this
     * @throws \LogicException if the container is readonly
     */
    public function set(string $path, mixed $value): static
    {
        $this->assertWritable();

        [$parent, $lastKey] = $this->navigateToParent($path, true);
        $parent[$lastKey] = $value;

        return $this;
    }

    /**
     * Get the value at $path, or return $default if not found.
     *
     * @param string $path
     * @param mixed $default
     * @return mixed
     */
    public function get(string $path, mixed $default = null): mixed
    {
        [$parent, $lastKey] = $this->navigateToParent($path, false);

        if ($parent === null) {
            return $default;
        }

        return $parent->offsetExists($lastKey) ? $parent[$lastKey] : $default;
    }

    /**
     * Check if a dotted path exists in the container.
     *
     * @param string $path
     * @return bool
     */
    public function has(string $path): bool
    {
        [$parent, $lastKey] = $this->navigateToParent($path, false);
        return $parent !== null && $parent->offsetExists($lastKey);
    }

    public function isEmpty(): bool
    {
        return count($this) === 0;
    }

    /**
     * Delete the value at $path.
     *
     * @param string $path
     * @return static
     * @throws \LogicException if the container is readonly
     */
    public function delete(string $path): static
    {
        $this->assertWritable();

        [$parent, $lastKey] = $this->navigateToParent($path, false);
        if ($parent?->offsetExists($lastKey)) {
            unset($parent[$lastKey]);
        }

        return $this;
    }

    /**
     * Merge another Container into this one.
     * Conflicting scalar values are collected into a "<key>_merged" array.
     *
     * @param ContainerInterface $other
     * @return static
     * @throws \LogicException if the container is readonly
     * @throws \InvalidArgumentException if $other is not a Container
     */
    public function merge(ContainerInterface $other): static
    {
        $this->assertWritable();

        if (!$other instanceof self) {
            throw new \InvalidArgumentException('Can only merge instances of Container');
        }

        foreach ($other as $key => $value) {
            if ($value instanceof self && $value->count() === 0) {
                continue;
            }

            if (!$this->offsetExists($key)) {
                $this[$key] = $value;
                continue;
            }

            $existing = $this[$key];

            if ($existing instanceof self && $value instanceof self) {
                $existing->merge($value);
            } else {
                $mergedKey = $key . '_merged';
                if (!$this->offsetExists($mergedKey)) {
                    $this[$mergedKey] = [];
                }
                $mergedArray = &$this[$mergedKey];
                if (!in_array($existing, $mergedArray, true)) {
                    $mergedArray[] = $existing;
                }
                $mergedArray[] = $value;

                unset($this[$key]);
            }
        }

        return $this;
    }

    /**
     * Return a deep readonly copy of the current container.
     *
     * @return static
     */
    public function asReadonlyCopy(): static
    {
        return $this->deepCopy($this)->setReadonly(true);
    }

    /**
     * Create a container from an associative array.
     *
     * @param array $data
     * @param bool $readonly
     * @return static
     */
    public static function fromArray(array $data, bool $readonly = false): static
    {
        $container = new static();

        foreach ($data as $k => $v) {
            $container[$k] = is_array($v)
                ? static::fromArray($v, $readonly)
                : $v;
        }

        return $container->setReadonly($readonly);
    }

    /**
     * Convert the container and its children to a plain PHP array.
     *
     * @return array
     */
    public function toArray(): array
    {
        $arr = [];
        foreach ($this as $k => $v) {
            $arr[$k] = ($v instanceof self) ? $v->toArray() : $v;
        }
        return $arr;
    }

    /**
     * Used for JSON serialization (e.g. json_encode).
     *
     * @return array<string, mixed>
    */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * String representation as pretty-printed JSON.
     *
     * @return string
    */
    public function __toString(): string
    {
        $json = json_encode($this->jsonSerialize(), JSON_PRETTY_PRINT);
        return $json === false ? '{}' : $json;
    }

    /**
     * @throws \LogicException if container is readonly
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        $this->assertWritable();
        parent::offsetSet($key, $value);
    }

    /**
     * @throws \LogicException if container is readonly
     */
    public function offsetUnset(mixed $key): void
    {
        $this->assertWritable();
        parent::offsetUnset($key);
    }

    /**
     * Truly cursed
     *
     * @return static
     */
    public function asReadonlyProxy(): static
    {
        $data = [];

        // Gather key-value pairs as raw array to avoid calling offsetSet()
        foreach ($this->deepCopy($this) as $key => $value) {
            $data[$key] = $value;
        }

        return new class ($data) extends Container {
            public function __construct(array $data)
            {
                parent::__construct();

                foreach ($data as $key => $value) {
                    parent::offsetSet($key, $value);
                }

                parent::setReadonly(true);
            }

            public function set(string $path, mixed $value): static
            {
                throw new \LogicException("Immutable proxy");
            }

            public function offsetSet($key, $value): void
            {
                throw new \LogicException("Immutable proxy");
            }

            public function offsetUnset($key): void
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
                // Ignored
                return $this;
            }
        };
    }

    /**
     * even more cursed
     *
     * @return static
     */
    public function deepReadonlyProxy(): static
    {
        $clone = $this->deepCopy($this);

        foreach ($clone as $k => $v) {
            if ($v instanceof self) {
                $clone[$k] = $v->deepReadonlyProxy();
            }
        }

        return $clone->asReadonlyProxy();
    }
}
