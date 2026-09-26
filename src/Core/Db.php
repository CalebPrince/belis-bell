<?php
declare(strict_types=1);

namespace Belis\Core;

use PDO;

/**
 * Thin PDO wrapper (CTL-INP-001, CTL-FW-001). Every value goes through bound
 * parameters. SQL text must be a literal in code: never build it from input.
 * tests/SqlLintTest.php scans the source for interpolated SQL.
 */
final class Db
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function fromEnv(): self
    {
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', '');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, Env::get('DB_USER', '') ?? '', Env::get('DB_PASS', '') ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
        return new self($pdo);
    }

    /** @param array<int|string,scalar|null> $params @return list<array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        $st = $this->statement($sql, $params);
        return $st->fetchAll();
    }

    /** @param array<int|string,scalar|null> $params @return array<string,mixed>|null */
    public function one(string $sql, array $params = []): ?array
    {
        $st = $this->statement($sql, $params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** @param array<int|string,scalar|null> $params */
    public function run(string $sql, array $params = []): int
    {
        $st = $this->statement($sql, $params);
        return $st->rowCount();
    }

    /** @param array<int|string,scalar|null> $params */
    private function statement(string $sql, array $params): \PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        $i = 1;
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $i++ : $key;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $st->bindValue($name, $value, $type);
        }
        $st->execute();
        return $st;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }
}
