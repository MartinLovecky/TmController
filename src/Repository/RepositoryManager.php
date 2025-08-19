<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Database\{Fluent, Table};

class RepositoryManager
{
    public function __construct(protected Fluent $fluent)
    {
    }

    public function insert(Table $table, array $updateData, string $where): void
    {
        $check = $this->getCheck($table, $where);

        if ($this->exists($table, $check['column'], $check['value'])) {
            return;
        }

        $this->fluent->query->insertInto($table->value)->values($updateData)->execute();
    }

    public function update(Table $table, array $updateData, string $where): void
    {
        $check = $this->getCheck($table, $where);

        if (!$this->exists($table, $check['column'], $check['value'])) {
            return;
        }

        $this->fluent->query->update($table->value)->set($updateData)->execute();
    }

    public function delete(Table $table, string $column, mixed $value): void
    {
        if (!$this->exists($table, $column, $value)) {
            return;
        }

        $this->fluent->query->deleteFrom($table->value)->where($column, $value)->execute();
    }

    public function get(
        Table $table,
        ?string $column = null,
        mixed $value = null,
        array $item = []
    ): mixed {
        if (!$this->exists($table, $column, $value)) {
            return null;
        }

        $stmt = $this->fluent->query->from($table->value)->where($column, $value);

        if (!$column && !$value && count($item) === 0) {
            return $stmt->fetchAll();
        } elseif (!$column && !$value && count($item) === 1) {
            return array_keys($stmt->fetchAll(...$item));
        } elseif (!$column && !$value && count($item) === 2) {
            return $stmt->fetchPairs(...$item);
        } elseif (!$column && !$value && count($item) > 2) {
            return $stmt->fetchAll(...$item);
        } elseif ($column && $value && count($item) <= 1) {
            return $stmt->fetch(...$item);
        } elseif ($column && $value && count($item) === 2) {
            return $stmt->fetchPairs(...$item);
        } else {
            return $stmt->fetchAll(...$item);
        }
    }

    private function exists(Table $table, string $column, mixed $value): bool
    {
        return $this->fluent->query
            ->from($table->value)
            ->select($column)
            ->where($column, $value)
            ->fetch($column) !== false;
    }

    private function getCheck(Table $table, string $c): array
    {
        return match ($table->value) {
            'players'       => $this->playerCheck($c),
            'challenges'    => $this->challengeCheck($c),
            'records'       => $this->recordsCheck($c),
            'players_extra' => $this->playersExtraCheck($c),
            'rs_karma'      => $this->rsKarmaCheck($c),
            'rs_rank'       => $this->rsRankCheck($c),
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

    private function playerCheck(string $check): array
    {
        return [
            'column' => 'playerID',
            'value'  => $check,
        ];
    }

    private function playersExtraCheck(string $check): array
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
     * Undocumented function
     *
     * @param string $check playerID|challangeID
     * @return array
     */
    private function rsKarmaCheck(string $check): array
    {
        return [
            'column' => 'playerID',
            'value'  => $check
        ];
    }

    private function rsRankCheck(string $check): array
    {
        return [
            'column' => 'playerID',
            'value'  => $check,
        ];
    }
}
