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

        if (ctype_digit((string)$lastKey)) {
            $lastKey = (int)$lastKey;
        }

        return $parent->offsetExists($lastKey) ? $parent[$lastKey] : $default;
    }

    /**
     * Get a value at the given dot-path and coerce it to a specific type.
     *
     * @param string $path Dot-path to the value.
     * @param string $type Target type: 'string', 'int', 'float', 'bool', 'array'.
     * @param mixed $default Default value if path does not exist or coercion fails (non-strict mode).
     * @param bool $strict Whether to throw an exception if coercion fails.
     *
     * @return mixed The value coerced to $type, or $default in non-strict mode.
     *
     * @throws \UnexpectedValueException If strict and coercion fails.
     * @throws \InvalidArgumentException If strict and unknown $type is requested.
     */
    public function getTyped(
        string $path,
        string $type,
        mixed $default = null,
        bool $strict = true
    ): mixed {
        $value = $this->get($path, $default);

        return $this->coerceValue($value, $type, $default, $strict);
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
     * Delete the value at $path.
     *
     * @param string $path
     * @return static
     */
    public function delete(string $path): static
    {
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
            $container[$k] = is_array($v)
            ? static::fromArray($v)
            : $v;
        }

        return $container;
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

        $filePath = Server::$jsonDir . "{$file}.json";

        return file_put_contents($filePath, $json) !== false;
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

    /**
     * Unified type coercion for getters.
     *
     * Converts a value to the requested type if possible. Supports strict mode.
     *
     * @param mixed $value The value to coerce.
     * @param string $type The target type: 'string', 'int', 'float', 'bool', 'array'.
     * @param mixed $default The default value to return if coercion fails in non-strict mode.
     * @param bool $strict Whether to throw exceptions if coercion fails.
     *
     * @return mixed The coerced value or $default if non-strict.
     *
     * @throws \UnexpectedValueException If strict and value cannot be coerced.
     * @throws \InvalidArgumentException If strict and unknown $type is requested.
     */
    protected function coerceValue(
        mixed $value,
        string $type,
        mixed $default = null,
        bool $strict = true
    ): mixed {
        if ($value instanceof self && $type === 'array') {
            return $value->toArray();
        }

        if ($value === null) {
            if ($strict) {
                throw new \UnexpectedValueException("Expected value of type '{$type}', got null");
            }
            return $default;
        }

        return match ($type) {
            'string' => match (true) {
                is_string($value) => $value,
                is_scalar($value) && !(is_bool($value)) => (string)$value,
                is_object($value) && method_exists($value, '__toString') => (string)$value,
                default => $strict ? throw new \UnexpectedValueException("Expected string-compatible value") : $default
            },
            'int' => match (true) {
                is_int($value) => $value,
                is_numeric($value) && (string)(int)$value === (string)$value => (int)$value,
                default => $strict ? throw new \UnexpectedValueException("Expected int-compatible value") : $default
            },
            'float' => match (true) {
                is_float($value) => $value,
                is_numeric($value) => (float)$value,
                default => $strict ? throw new \UnexpectedValueException("Expected float-compatible value") : $default
            },
            'bool' => match (true) {
                is_bool($value) => $value,
                in_array($value, [0, 1, '0', '1', 'true', 'false'], true)
                => filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
                default => $strict ? throw new \UnexpectedValueException("Expected bool-compatible value") : $default
            },
            'array' => is_array($value) ? $value :
            ($strict ? throw new \UnexpectedValueException("Expected array") : $default),
            default => $strict ? throw new \InvalidArgumentException("Unknown type '{$type}'") : $default
        };
    }
}
