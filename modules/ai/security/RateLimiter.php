<?php

declare(strict_types=1);

namespace Forsa\Ai\Security;

use Forsa\Database;

/**
 * PRD §15.1/§38 — rate limit per user. A simple rolling-window count against
 * ai_messages (joined to ai_conversations for user_id, since messages don't
 * carry it directly) rather than an in-memory counter — PHP-FPM/CLI workers
 * don't share memory across requests, so anything not backed by the
 * database (or Redis, which this project doesn't run) wouldn't actually
 * limit anything across concurrent requests.
 */
final class RateLimiter
{
    public function __construct(
        private readonly int $maxRequests = 10,
        private readonly int $windowSeconds = 60,
    ) {
    }

    public function tooManyRequests(int $userId): bool
    {
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) AS c
             FROM forsa_ai_messages m
             JOIN forsa_ai_conversations c ON c.id = m.conversation_id
             WHERE c.user_id = :user_id
               AND m.role = 'user'
               AND m.created_at > now() - make_interval(secs => :window)"
        );
        $stmt->execute(['user_id' => $userId, 'window' => $this->windowSeconds]);
        return (int) $stmt->fetch()['c'] >= $this->maxRequests;
    }
}
