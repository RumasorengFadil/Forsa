<?php

declare(strict_types=1);

namespace Forsa\Ai\Repositories;

use Forsa\Database;

final class AiMessageRepository
{
    public function append(int $conversationId, string $role, ?string $content, ?string $toolCallId = null): array
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO forsa_ai_messages (conversation_id, role, content, tool_call_id)
             VALUES (:conversation_id, :role, :content, :tool_call_id)
             RETURNING id, conversation_id, role, content, tool_call_id, created_at'
        );
        $stmt->execute([
            'conversation_id' => $conversationId,
            'role' => $role,
            'content' => $content,
            'tool_call_id' => $toolCallId,
        ]);
        return $stmt->fetch();
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $conversationId, int $limit = 30): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, role, content, tool_call_id, created_at
             FROM forsa_ai_messages
             WHERE conversation_id = :conversation_id
             ORDER BY created_at ASC
             LIMIT :limit'
        );
        $stmt->bindValue('conversation_id', $conversationId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
