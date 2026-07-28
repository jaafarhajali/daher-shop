<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Base model — thin PDO helpers shared by all models.
 * Every query in the application goes through prepared statements.
 */
abstract class Model
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** @return array<int, array<string, mixed>> */
    protected function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    protected function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Single scalar value (COUNT, SUM, ...). */
    protected function fetchValue(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    /** @return int affected row count */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    protected function lastId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    /**
     * Server-side pagination.
     *
     * @param string $sql       full SELECT without LIMIT
     * @param string $countSql  matching SELECT COUNT(*) query
     * @return array{rows: array, total: int, page: int, pages: int, per_page: int}
     */
    protected function paginate(
        string $sql,
        string $countSql,
        array $params,
        int $page,
        int $perPage = 15
    ): array {
        $total = (int) $this->fetchValue($countSql, $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page  = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        // LIMIT/OFFSET are integers derived above — safe to inline.
        $rows = $this->fetchAll($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'pages'    => $pages,
            'per_page' => $perPage,
        ];
    }

    /** Run $fn inside a transaction; rolls back on any exception. */
    protected function transaction(callable $fn): mixed
    {
        $this->db->beginTransaction();
        try {
            $result = $fn();
            $this->db->commit();

            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
