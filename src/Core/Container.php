<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core;

use ArrayObject;
use ArrayIterator;
use Yuhzel\TmController\App\Aseco;
use Yuhzel\TmController\Core\Contracts\ContainerInterface;
use Yuhzel\TmController\Core\Traits\{
    ArrayForwarderTrait,
    DotPathTrait,
    ReadonlyTrait
};

/**
 * Recursive container supporting dot-path access, readonly state, and deep merging.
 *
 * Implements ArrayAccess<string, mixed> for direct property-style access.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class Container extends ArrayObject implements ContainerInterface
{
    use DotPathTrait;
    use ReadonlyTrait;
    use ArrayForwarderTrait;

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
        return $this->count() === 0;
    }

    public function count(): int
    {
        return parent::count();
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this);
    }

    public function first(): mixed
    {
        foreach ($this as $value) {
            return $value;
        }
        return null;
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
     * Merge another container into this one.
     *
     * Conflicting scalar values are collected into an array under the key "<key>_merged".
     *
     * @param ContainerInterface $other
     * @return static
     * @throws \LogicException If this container is readonly
     * @throws \InvalidArgumentException If $other is not a Container instance
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
     * Create a container recursively from an array.
     *
     * @param array<string, mixed> $data
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
     * Decode JSON string and convert to container.
     *
     * @param string $json JSON string to decode
     * @param bool $readonly Set readonly flag on result
     * @return static
     * @throws \InvalidArgumentException If JSON is invalid
     */
    public static function fromJsonString(string $json, bool $readonly = false): static
    {
        $data = Aseco::safeJsonDecode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return self::fromArray($data, $readonly);
    }

    /**
     * Load JSON from a file and convert to container.
     *
     * @param string $filePath Path to JSON file
     * @param bool $readonly Set readonly flag on result
     * @return static
     * @throws \RuntimeException If file cannot be read or contents are invalid
     */
    public static function fromJsonFile(string $filePath, bool $readonly = false): static
    {
        $json = Aseco::safeFileGetContents($filePath);

        if (!$json) {
            throw new \RuntimeException("Invalid filePath: {$filePath}");
        }

        return self::fromJsonString($json, $readonly);
    }

    /**
     * Recursively convert container and nested containers to arrays.
     *
     * @return array<string, mixed>
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
     * Recursively convert container and nested containers to arrays.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Return a pretty-printed JSON representation of this container.
     *
     * @return string JSON string or '{}' if encoding fails
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
}
