<?php

declare(strict_types=1);

namespace Yuhzel\TmController\App\Service;

class Arr
{
    /**
     * Determine if an array is associative.
     *
     * An array is considered associative if any of its keys are non-integers
     *
     * @param array $array The array to check.
     * @return bool True if associative, false if sequential.
     */
    public static function isAssoc(array $array): bool
    {
        foreach ($array as $key => $_) {
            if (!is_int($key) || $key < 0 || $key >= count($array)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Remove elements from an array by their indexes.
     * The resulting array is reindexed (0,1,2,...).
     *
     * @param array $array The source array.
     * @param array $indexesToRemove Indexes to remove.
     * @return array The array with given indexes removed and reindexed.
     */
    public static function removeIndexes(array $array, array $indexesToRemove): array
    {
        foreach ($indexesToRemove as $index) {
            unset($array[$index]);
        }
        return array_values($array);
    }

    /**
     * Pick only specific keys from an array.
     *
     * @param array $source The source array.
     * @param array $keys Keys to pick.
     * @return array A new array containing only the picked keys (preserving original keys).
     */
    public static function pick(array $source, array $keys): array
    {
        return array_intersect_key($source, array_flip($keys));
    }

    /**
     * Exclude specific keys from an array.
     *
     * Preserves the original keys.
     *
     * @param array $source The source array.
     * @param array $keys Keys to exclude.
     * @return array The array without the given keys.
     */
    public static function except(array $source, array $keys): array
    {
        return array_diff_key($source, array_flip($keys));
    }

    /**
     * Flatten a multi-dimensional array into a single-level array.
     *
     * @param array $array The multi-dimensional array.
     * @return array A flat array containing all values.
     */
    public static function flatten(array $array): array
    {
        $result = [];

        array_walk_recursive($array, function ($value) use (&$result) {
            $result[] = $value;
        });

        return $result;
    }

    /**
     * Convert a multi-dimensional array into a dot-notated array.
     *
     * @param array $array The source array.
     * @param string $prepend Used internally for recursion.
     * @return array The dot-notated array.
     */
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

    /**
     * Convert a dot-notated array back into a multi-dimensional array.
     *
     * @param array $dotArray The dot-notated array.
     * @return array The expanded multi-dimensional array.
     */
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
