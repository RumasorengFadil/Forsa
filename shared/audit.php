<?php

declare(strict_types=1);

use Forsa\Database;

function audit_log(?int $userId, string $action, ?string $entityType = null, ?string $entityId = null, ?array $oldData = null, ?array $newData = null): void
{
    $pdo = Database::connection();
    $stmt = $pdo->prepare(
        'INSERT INTO forsa_audit_logs (user_id, action, entity_type, entity_id, old_data, new_data, ip_address, user_agent)
         VALUES (:user_id, :action, :entity_type, :entity_id, :old_data, :new_data, :ip_address, :user_agent)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'old_data' => $oldData !== null ? json_encode($oldData) : null,
        'new_data' => $newData !== null ? json_encode($newData) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ]);
}
