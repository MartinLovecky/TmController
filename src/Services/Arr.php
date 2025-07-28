<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Services;

class Arr
{
    public static function isAssoc(array $array): bool
    {
        foreach ($array as $key => $_) {
            if (!is_int($key) || $key < 0 || $key >= count($array)) {
                return true;
            }
        }

        return false;
    }

    public static function removeIndexes(array $array, array $indexesToRemove): array
    {
        foreach ($indexesToRemove as $index) {
            unset($array[$index]);
        }
        return array_values($array);
    }

    public static function pick(array $source, array $keys): array
    {
        return array_intersect_key($source, array_flip($keys));
    }

    public static function except(array $source, array $keys): array
    {
        return array_diff_key($source, array_flip($keys));
    }

    public static function flatten(array $array): array
    {
        $result = [];

        array_walk_recursive($array, function ($value) use (&$result) {
            $result[] = $value;
        });

        return $result;
    }

    public static function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $newKey = $prepend . $key;
            if (is_array($value) && !empty($value)) {
                $results += static::dot($value, $newKey . '.');
            } else {
                $results[$newKey] = $value;
            }
        }

        return $results;
    }

    public static function undot(array $dotArray): array
    {
        $result = [];

        foreach ($dotArray as $dotKey => $value) {
            $keys = explode('.', $dotKey);
            $temp = &$result;

            foreach ($keys as $key) {
                if (!isset($temp[$key]) || !is_array($temp[$key])) {
                    $temp[$key] = [];
                }
                $temp = &$temp[$key];
            }

            $temp = $value;
        }

        return $result;
    }
}
