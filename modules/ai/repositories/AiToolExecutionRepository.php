<?php

declare(strict_types=1);

namespace Forsa\Ai\Repositories;

use Forsa\Database;

final class AiToolExecutionRepository
{
    public function log(
        int $conversationId,
        int $userId,
        string $toolName,
        array $arguments,
        ?array $semanticQuery,
        ?array $resultSummary,
        int $executionTimeMs,
        string $status,
    ): void {
        $stmt = Database::connection()->prepare(
            'INSERT INTO forsa_ai_tool_executions
                (conversation_id, user_id, tool_name, arguments_json, semantic_query_json, result_summary_json, execution_time_ms, status)
             VALUES (:conversation_id, :user_id, :tool_name, :arguments_json, :semantic_query_json, :result_summary_json, :execution_time_ms, :status)'
        );
        $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'tool_name' => $toolName,
            'arguments_json' => json_encode($arguments, JSON_UNESCAPED_UNICODE),
            'semantic_query_json' => $semanticQuery !== null ? json_encode($semanticQuery, JSON_UNESCAPED_UNICODE) : null,
            'result_summary_json' => $resultSummary !== null ? json_encode($resultSummary, JSON_UNESCAPED_UNICODE) : null,
            'execution_time_ms' => $executionTimeMs,
            'status' => $status,
        ]);
    }
}
