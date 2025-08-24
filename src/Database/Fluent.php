<?php

declare(strict_types=1);

namespace Yuhzel\TmController\Database;

use PDO;
use Envms\FluentPDO\Query;
use Yuhzel\TmController\App\Service\Aseco;
use Yuhzel\TmController\Database\Table;
use Yuhzel\TmController\Services\Server;

class Fluent
{
    public PDO $pdo;
    public Query $query;

    public function __construct()
    {
        $config = $this->loadConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s;sslmode=verify-ca;sslrootcert=ca.pem',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $config['login'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //PDO::FETCH_CLASS,
                PDO::ATTR_STRINGIFY_FETCHES => false
            ]);
            $this->query = new Query($this->pdo);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Database connection failed:' . $e->getMessage());
        }
    }

    public function executeFile(Table $table): void
    {
        $table = strtolower($table->value);
        $filePath = Server::$sqlDir . "{$table}.sql";

        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Invalid file path: '{$filePath}'.");
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $table)) {
            throw new \InvalidArgumentException("Invalid file name: '{$table}'.");
        }

        $sql = file_get_contents($filePath);

        if ($sql === false) {
            throw new \RuntimeException("Failed to read the contents of the file at path '{$filePath}'.");
        }
        if ($sql === '') {
            throw new \RuntimeException("The file at path '{$filePath}' is empty.");
        }
        try {
            $this->pdo->exec($sql);
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Error executing SQL file '{$table}.sql':" . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function dropTable(Table $table): void
    {
        $table = strtolower($table->value);
        try {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                "Error dropping table '{$table}':" . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function loadConfig(): array
    {
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'local');
        $prefix = strtoupper($env); // LOCAL or ONLINE

        return [
            'host'     => $_ENV["DB_{$prefix}_HOST"],
            'port'     => $_ENV["DB_{$prefix}_PORT"],
            'database' => $_ENV["DB_{$prefix}_DATABASE"],
            'login'    => $_ENV["DB_{$prefix}_LOGIN"],
            'password' => $_ENV["DB_{$prefix}_PASSWORD"],
            'charset'  => $_ENV["DB_{$prefix}_CHARSET"] ?? 'utf8mb4',
        ];
    }
}
