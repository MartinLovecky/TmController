<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Repository;

use Yuhzel\TmController\Core\Container;
use Yuhzel\TmController\Database\{Fluent, Table};

class RepositoryManager
{
    public function __construct(protected Fluent $fluent)
    {
    }

    public function insert(Table $table, Container $c): void
    {
        $prepared = $this->getData($table, $c);
        $check = $prepared['check'];

        if ($this->exists($table, $check['column'], $check['value'])) {
            return;
        }

        $data = $prepared['data'];

        $this->fluent->query->insertInto($table->value)->values($data)->execute();
    }

    public function update(Table $table, Container $c): void
    {
        $prepared = $this->getData($table, $c);
        $check = $prepared['check'];

        if (!$this->exists($table, $check['column'], $check['value'])) {
            return;
        }

        $data = $prepared['data'];

        $this->fluent->query->update($table->value)->set($data)->execute();
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

    public function exists(Table $table, string $column, mixed $value): bool
    {
        return $this->fluent->query
            ->from($table->value)
            ->select($column)
            ->where($column, $value)
            ->fetch($column) !== false;
    }

    private function getData(Table $table, Container $c): array
    {
        return match ($table->value) {
            'players' => $this->preparePlayerData($c),
            'challenges' => $this->prepareChallengeData($c),
            'records' => $this->prepareRecordsData($c),
            'players_extra' => $this->preparePlayersExtraData($c),
            'rs_karma' => $this->prepareRsKarmaData($c),
            'rs_rank' => $this->prepareRsRankData($c),
            default => throw new \InvalidArgumentException(
                "No data preparation method defined for table: {$table->value}"
            )
        };
    }

    private function prepareChallengeData(Container $c): array
    {
        return [
            'data' => [
                'Uid'         => $c->get('UId'),
                'Name'        => $c->get('Name'),
                'Author'      => $c->get('Author'),
                'Environment' => $c->get('Environnement')
            ],
            'check' => [
                'column' => 'Uid',
                'value'  => $c->get('UId'),
            ]
        ];
    }

    private function preparePlayerData(Container $c): array
    {
        return [
            'data' => [
                'Login'      => $c->get('Login'),
                'Game'       => $c->get('Game'),
                'NickName'   => $c->get('NickName'),
                'playerID'   => $c->get('Login'),
                'Nation'     => $c->get('Nation'),
                'Wins'       => $c->get('Wins', 0),
                'TimePlayed' => $c->get('TimePlayed', 0),
                'TeamName'   => $c->get('TeamName', ''),
                'LastSeen'   => date('Y-m-d H:i:s'),
            ],
            'check' => [
                'column' => 'playerID',
                'value'  => $c->get('Login'),
            ]
        ];
    }

    private function preparePlayersExtraData(Container $c): array
    {
        return [
        'data' => [
            'cps'        => $c->get('Cps', -1),
            'dedicps'    => $c->get('Dedicps', -1),
            'donations'  => $c->get('Donations', 0),
            'style'      => $c->get('Style', ''),
            'panels'     => $c->get('Panels', ''),
            'playerID'   => $c->get('PlayerID', ''),
        ],
        'check' => [
            'column' => 'playerID',
            'value'  => $c->get('PlayerID'),
        ]
        ];
    }

    private function prepareRecordsData(Container $c): array
    {
        return [
        'data' => [
            'ChallengeId' => $c->get('Challengeid', ''),
            'Times'       => json_encode($c->get('Times', [])),
            'Date'        => date('Y-m-d H:i:s'),
            'Checkpoints' => $c->get('Checkpoints', null),
        ],
        'check' => [
            'column' => 'ChallengeId',
            'value'  => $c->get('ChallengeId'),
        ]
        ];
    }

    private function prepareRsKarmaData(Container $c): array
    {
        return [
        'data' => [
            'ChallengeId'       => $c->get('ChallengeId', ''),
            'playerID'          => $c->get('PlayerId', ''),
            'Score'             => $c->get('score', 0),
            'Plus'              => $c->get('plus', 0),
            'PlusPlus'          => $c->get('plusplus', 0),
            'PlusPlusPlus'      => $c->get('plusplusplus', 0),
            'Minus'             => $c->get('minus', 0),
            'MinusMinus'        => $c->get('minusminus', 0),
            'MinusMinusMinus'   => $c->get('minusminusminus', 0),
        ],
        'check' => [
            'column' => 'playerID',
            'value'  => $c->get('PlayerId') . '|' . $c->get('ChallengeId'),
        ]
        ];
    }

    private function prepareRsRankData(Container $c): array
    {
        return [
        'data' => [
            'playerID'  => $c->get('PlayerId', ''),
            'avg_score' => $c->get('avg_score', 0.000),
        ],
        'check' => [
            'column' => 'playerID',
            'value'  => $c->get('PlayerId'),
        ]
        ];
    }
}
