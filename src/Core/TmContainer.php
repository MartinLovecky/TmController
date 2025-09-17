<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core;

use ArrayObject;
use ArrayIterator;
use Yuhzel\TmController\App\Service\{Aseco, Server};
use Yuhzel\TmController\Core\Contracts\ContainerInterface;
use Yuhzel\TmController\Core\Traits\{
    ArrayForwarderTrait,
    DotPathTrait
};

/**
 * Recursive container supporting dot-path access
 *
 * Implements ArrayAccess<string, mixed> for direct property-style access.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class TmContainer extends ArrayObject implements ContainerInterface
{
    use DotPathTrait;
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
     */
    public function set(string $path, mixed $value): static
    {
        [$parent, $lastKey] = $this->navigateToParent($path, true);

        if ($parent === null) {
            return $this;
        }

        if (ctype_digit((string)$lastKey)) {
            $lastKey = (int)$lastKey;
        }

        if ($parent instanceof self || is_array($parent)) {
            $parent[$lastKey] = $value;
        }

        return $this;
    }

    /**
     * Set multiple key => value pairs at once.
     *
     * Supports dot-path keys and method chaining.
     *
     * @param array<string, mixed> $values
     * @return static
     */
    public function setMultiple(array $values): static
    {
        foreach ($values as $path => $value) {
            $this->set($path, $value);
        }
        return $this;
    }

    /**
     * Get a value at a dot-path, with default.
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

        if (ctype_digit((string)$lastKey)) {
            $lastKey = (int)$lastKey;
        }

        return match (true) {
            $parent instanceof self => $parent->offsetExists($lastKey) ? $parent[$lastKey] : $default,
            is_array($parent) => array_key_exists($lastKey, $parent) ? $parent[$lastKey] : $default,
            default => $default,
        };
    }

    /**
     * Check if a dotted path exists.
     *
     * @param string $path
     * @return bool
     */
    public function has(string $path): bool
    {
        [$parent, $lastKey] = $this->navigateToParent($path, false);

        if ($parent === null) {
            return false;
        }

        if (ctype_digit((string)$lastKey)) {
            $lastKey = (int)$lastKey;
        }

        return match (true) {
            $parent instanceof self => $parent->offsetExists($lastKey),
            is_array($parent) => array_key_exists($lastKey, $parent),
            default => false,
        };
    }

    /**
     * Delete a value at a dot-path.
     *
     * @param string $path
     * @return static
     */
    public function delete(string $path): static
    {
        [$parent, $lastKey] = $this->navigateToParent($path, false);

        if ($parent === null) {
            return $this;
        }

        if (ctype_digit((string)$lastKey)) {
            $lastKey = (int)$lastKey;
        }

        if ($parent instanceof self || is_array($parent)) {
            unset($parent[$lastKey]);
        }

        return $this;
    }

    /**
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * easy access to count
     *
     * @return integer
     */
    public function count(): int
    {
        return parent::count();
    }

    /**
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this);
    }

    /**
     *
     * @return mixed
     */
    public function first(): mixed
    {
        foreach ($this as $value) {
            return $value;
        }
        return null;
    }

    /**
     * Merge another container into this one.
     *
     * Conflicting scalar values are collected into an array under the key "<key>_merged".
     *
     * @param ContainerInterface $other
     * @return static
     * @throws \InvalidArgumentException If $other is not a Container instance
     */
    public function merge(ContainerInterface $other): static
    {
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
     * @return static
     */
    public static function fromArray(array $data = []): static
    {
        $container = new static();

        foreach ($data as $k => $v) {
            if (ctype_digit((string)$k)) {
                $k = (int) $k;
            }

            $container[$k] = is_array($v)
            ? static::fromArray($v)
            : $v;
        }

        return $container;
    }

    /**
     * Recursively convert container to array.
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
     * Decode JSON string and convert to container.
     *
     * @param string $json JSON string to decode
     * @return static
     * @throws \InvalidArgumentException If JSON is invalid
     */
    public static function fromJsonString(string $json): static
    {
        $data = Aseco::safeJsonDecode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON: ' . json_last_error_msg());
        }

        return self::fromArray($data);
    }

    /**
     * Load JSON from a file and convert to container.
     *
     * @param string $filePath Path to JSON file
     * @return static
     * @throws \RuntimeException If file cannot be read or contents are invalid
     */
    public static function fromJsonFile(string $filePath): static
    {
        $json = Aseco::safeFileGetContents($filePath);

        if (!$json) {
            throw new \RuntimeException("Invalid filePath: {$filePath}");
        }

        return self::fromJsonString($json);
    }

    /**
     * Save this container to a JSON file.
     * - save location TmController/public/json/
     * @param string $file without .json
     * @return bool true on success
     */
    public function saveToJsonFile(string $file): bool
    {
        $json = json_encode(
            $this->jsonSerialize(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            return false;
        }

        return file_put_contents(Server::$jsonDir . "{$file}.json", $json) !== false;
    }

    /**
     * Update a value at a given dot-path inside a JSON file.
     *
     * Loads the JSON → applies update → writes back.
     *
     * @param string $filePath Path to JSON file
     * @param string $path Dot-path inside the JSON
     * @param mixed $value New value
     * @return bool
     */
    public static function updateJsonFile(string $filePath, string $path, mixed $value): bool
    {
        $container = self::fromJsonFile($filePath);
        $container->set($path, $value);

        return $container->saveToJsonFile($filePath);
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
     * overwrite offsetSet
     *
     * @param mixed $key
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        parent::offsetSet($key, $value);
    }

    /**
     * overwrite offsetUnset
     *
     * @param mixed $key
     * @return void
     */
    public function offsetUnset(mixed $key): void
    {
        parent::offsetUnset($key);
    }
}
