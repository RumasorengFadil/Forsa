<?php

declare(strict_types=1);

namespace Forsa\Ai\Repositories;

use Forsa\Database;
use PDO;

final class AiConversationRepository
{
    public function create(int $userId, ?string $title): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO forsa_ai_conversations (user_id, title) VALUES (:user_id, :title)
             RETURNING id, user_id, title, status, created_at, updated_at'
        );
        $stmt->execute(['user_id' => $userId, 'title' => $title]);
        return $stmt->fetch();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, user_id, title, status, created_at, updated_at
             FROM forsa_ai_conversations WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listForUser(int $userId, int $limit = 50): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, status, created_at, updated_at
             FROM forsa_ai_conversations
             WHERE user_id = :user_id
             ORDER BY updated_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function touch(int $id): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE forsa_ai_conversations SET updated_at = now() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    public function setTitleIfEmpty(int $id, string $title): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE forsa_ai_conversations SET title = :title WHERE id = :id AND title IS NULL'
        );
        $stmt->execute(['id' => $id, 'title' => $title]);
    }
}
