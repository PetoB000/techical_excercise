<?php

declare(strict_types=1);

namespace AcmeLearn\Importer;

use PDO;

/**
 * Persists users to the platform's user store.
 */
final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string, string|int> $user
     */
    public function insert(array $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (hr_id, first_name, last_name, email, department, is_active)
             VALUES (:hr_id, :first_name, :last_name, :email, :department, :is_active)'
        );

        $stmt->execute([
            ':hr_id'      => (string) $user['hr_id'],
            ':first_name' => $user['first_name'],
            ':last_name'  => $user['last_name'],
            ':email'      => $user['email'],
            ':department' => $user['department'] ?? '',
            ':is_active'  => (int) $user['is_active'],
        ]);
    }

    /**
     * Insert or update a user by hr_id in a single statement.
     * @param array<string, string|int> $data
     */
    public function upsert(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (hr_id, first_name, last_name, email, department, is_active)
             VALUES (:hr_id, :first_name, :last_name, :email, :department, :is_active)
             ON CONFLICT(hr_id) DO UPDATE SET
                 first_name = excluded.first_name,
                 last_name  = excluded.last_name,
                 email      = excluded.email,
                 department = excluded.department,
                 is_active  = excluded.is_active'
        );

        $stmt->execute([
            ':hr_id'      => (string) $data['hr_id'],
            ':first_name' => $data['first_name'],
            ':last_name'  => $data['last_name'],
            ':email'      => $data['email'],
            ':department' => $data['department'] ?? '',
            ':is_active'  => (int) $data['is_active'],
        ]);
    }

    /**
     * Return all hr_ids currently in the store as a flat array.
     * @return string[]
     */
    public function findAllHrIds(): array
    {
        return $this->pdo->query('SELECT hr_id FROM users')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Update an existing user's fields by their HR ID (used during CSV import).
     *
     * @param array<string, string|int> $data
     */
    public function updateByHrId(string $hrId, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
             SET first_name = :first_name,
                 last_name  = :last_name,
                 email      = :email,
                 department = :department,
                 is_active  = :is_active
             WHERE hr_id = :hr_id'
        );

        $stmt->execute([
            ':hr_id'      => $hrId,
            ':first_name' => $data['first_name'],
            ':last_name'  => $data['last_name'],
            ':email'      => $data['email'],
            ':department' => $data['department'] ?? '',
            ':is_active'  => (int) $data['is_active'],
        ]);
    }

    /**
     * Update a subset of fields on a user by their store ID (used by the updateUser mutation).
     *
     * @param array<string, string|int> $columns Snake_case column => value pairs to update.
     */
    public function updateById(int $id, array $columns): void
    {
        $setClauses = implode(', ', array_map(
            static fn (string $col): string => "{$col} = :{$col}",
            array_keys($columns),
        ));

        $stmt = $this->pdo->prepare("UPDATE users SET {$setClauses} WHERE id = :id");
        $stmt->execute(array_merge([':id' => $id], $columns));
    }

    public function existsByHrId(string $hrId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE hr_id = :hr_id');
        $stmt->execute([':hr_id' => $hrId]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, hr_id, first_name, last_name, email, department, is_active
             FROM users ORDER BY id LIMIT :limit OFFSET :offset'
        );
        $stmt->execute([':limit' => $limit, ':offset' => $offset]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, string|int>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, hr_id, first_name, last_name, email, department, is_active FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
