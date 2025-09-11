<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Database\{Fluent, Table};

class RepositoryManager
{
    public function __construct(protected Fluent $fluent)
    {
    }

    /**
     * Insert a row into the specified table if it does not already exist.
     *
     * @param Table $table The table to insert the data into.
     * @param array $insertData Associative array of column => value.
     *              All columns without default values must be provided.
     * @param mixed $value of playerID or Uid or playerID|Uid
     * @return void
     */
    public function insert(Table $table, array $insertData, mixed $conditions): void
    {
        $check = $this->getCheck($table, $conditions);
        $column = $check['where'] ?? $check['column'];
        $value = $check['value'] ?? null;

        if ($this->exists($table, $column, $value)) {
            return;
        }

        $this->fluent->query->insertInto($table->value)->values($insertData)->execute();
    }

    /**
     * Update a row in the specified table if it already exist.
     *
     * @param Table $table The table in which the row should be updated
     * @param array $updateData Associative array of column => value.
     *              Only provide the columns you want to update
     * @param mixed $value of playerID or Uid or playerID|Uid
     * @return void
     */
    public function update(Table $table, array $updateData, mixed $conditions): void
    {
        $check = $this->getCheck($table, $conditions);
        $column = $check['where'] ?? $check['column'];
        $value = $check['value'] ?? null;

        if (!$this->exists($table, $column, $value)) {
            return;
        }

        $stmt = $this->fluent->query
            ->update($table->value)
            ->set($updateData);

        if (isset($check['where'])) {
            foreach ($check['where'] as $col => $val) {
                $stmt->where($col, $val);
            }
        } else {
            $stmt->where($check['column'], $check['value']);
        }

        $stmt->execute();
    }

    public function delete(Table $table, string $column, mixed $value): void
    {
        if (!$this->exists($table, $column, $value)) {
            return;
        }

        $this->fluent->query->deleteFrom($table->value)->where($column, $value)->execute();
    }

    /**
     * Retrieve data from a table with optional filtering and column selection.
     *
     * Behavior based on $item:
     * - []          → all columns
     * - [col]       → single column
     * - [col1, col2] → key-value pairs
     * - [col1, col2, ...] → selected columns
     *
     * If $column/$value are provided, only the matching row is returned.
     *
     * @param Table       $table   The table to query
     * @param mixed       $column  Optional column to filter by
     * @param mixed       $value   Value to filter by
     * @param array       $item    Columns to return (0=all, 1=single, 2=key-value, >2=selected)
     * @return mixed  Array (rows, key-value pairs, or selected columns),
     *                scalar (int, float, string, bool for single column), or null (no match)
     */
    public function fetch(
        Table $table,
        mixed $column = null,
        mixed $value = null,
        array $item = []
    ): mixed {
        if (!$this->exists($table, $column, $value)) {
            return null;
        }

        $stmt = $this->fluent->query->from($table->value)->where($column, $value);

        $count = count($item);

        if ($count === 0) {
            return $column ? $stmt->fetch() : $stmt->fetchAll();
        }

        if ($count === 1) {
            return $column ? $stmt->fetch(...$item) : array_keys($stmt->fetchAll(...$item));
        }

        if ($count === 2) {
            return $stmt->fetchPairs(...$item);
        }

        return $column ? $stmt->fetchAll(...$item) : $stmt->fetchAll(...$item);
    }

    private function exists(Table $table, mixed $column, mixed $value): bool
    {
        $stmt = $this->fluent->query->from($table->value);

        if (is_array($column)) {
            foreach ($column as $col => $val) {
                $stmt->where($col, $val);
            }
        } elseif ($column !== null) {
            $stmt->where($column, $value);
        }

        return $stmt->fetch() !== false;
    }

    private function getCheck(Table $table, string $c): array
    {
        return match ($table->value) {
            'players'       => $this->checkPlayerID($c),
            'challenges'    => $this->challengeCheck($c),
            'records'       => $this->recordsCheck($c),
            'players_extra' => $this->checkPlayerID($c),
            'rs_karma'      => $this->rsKarmaCheck($c),
            'rs_rank'       => $this->checkPlayerID($c),
            default => throw new \InvalidArgumentException(
                "No data preparation method defined for table: {$table->value}"
            )
        };
    }

    private function challengeCheck(string $check): array
    {
        return [
            'column' => 'Uid',
            'value'  => $check,
        ];
    }

    private function checkPlayerID(string $check): array
    {
        return [
            'column' => 'playerID',
            'value'  => $check,
        ];
    }

    private function recordsCheck(string $check): array
    {
        return [
            'column' => 'ChallengeId',
            'value'  => $check,
        ];
    }

    /**
     *
     * @param string $check playerID|challangeID
     * @return array
     */
    private function rsKarmaCheck(string $check): array
    {
        if (is_string($check)) {
            [$playerID, $challengeId] = explode('|', $check, 2);
        } elseif (is_array($check)) {
            $playerID = $check['playerID'] ?? '';
            $challengeId = $check['ChallengeId'] ?? '';
        }

        return [
            'where' => [
                'playerID'    => $playerID,
                'ChallengeId' => $challengeId
            ]
        ];
    }
}
