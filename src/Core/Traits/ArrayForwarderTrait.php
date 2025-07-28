<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Core\Traits;

use Yuhzel\TmController\Services\Arr;

trait ArrayForwarderTrait
{
    public function __call(string $name, array $arguments): mixed
    {
        if (!method_exists($this, 'toArray')) {
            throw new \BadMethodCallException('toArray method is required to use ArrayFunctionForwarderTrait.');
        }

        // Handle native array functions
        if (in_array($name, $this->allowedArrayFunctions(), true) && function_exists($name)) {
            array_unshift($arguments, $this->toArray());
            return $name(...$arguments);
        }

        // Handle Arr utility functions prefixed with 'arr_'
        if (str_starts_with($name, 'arr_')) {
            $method = substr($name, 4);

            if (!in_array($method, $this->allowedArrMethods(), true)) {
                throw new \BadMethodCallException("Arr method {$method} is not allowed.");
            }

            if (!method_exists(Arr::class, $method)) {
                throw new \BadMethodCallException("Arr method {$method} does not exist.");
            }

            array_unshift($arguments, $this->toArray());

            return Arr::$method(...$arguments);
        }

        throw new \BadMethodCallException("Method {$name} does not exist or is not allowed.");
    }

    /**
     * DONT convert this into attribute keep it as method
     *
     * @return array
    */
    protected function allowedArrMethods(): array
    {
        return [
            'isAssoc',
            'removeIndexes',
            'pick',
            'except',
            'flatten',
            'dot',
            'undot',
        ];
    }

    /**
     * DONT convert this into attribute keep it as method
     *
     * @return array
    */
    protected function allowedArrayFunctions(): array
    {
        return [
            'array_keys',
            'array_values',
            'array_filter',
            'array_map',
            'array_reverse',
            'array_chunk',
            'array_unique',
            'array_column',
        ];
    }
}
