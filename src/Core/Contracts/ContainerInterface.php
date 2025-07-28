<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Contracts;

use Countable;
use ArrayAccess;
use Traversable;
use JsonSerializable;
use IteratorAggregate;

interface ContainerInterface extends ArrayAccess, JsonSerializable, Countable, IteratorAggregate
{
    public function set(string $path, mixed $value): static;
    public function get(string $path, mixed $default = null): mixed;
    public function has(string $path): bool;
    public function delete(string $path): static;
    public function count(): int;
    public function getIterator(): Traversable;
    public function merge(ContainerInterface $other): static;
    public function toArray(): array;
}
