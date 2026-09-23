<?php

declare(strict_types=1);

namespace Forsa\Ai\Cache;

/**
 * PRD §20 — cache for repeated query_forsa calls. Key is
 * `ai:{user_scope_hash}:{semantic_query_hash}` exactly as PRD specifies, so
 * two users with different RBAC scopes (or the same user before/after a
 * scope change) never share a cache entry — "Cache tidak boleh bocor antar
 * security scope" is enforced by construction, not by convention.
 *
 * File-based under storage/ai/cache/ (PRD §41's own folder layout lists
 * storage/ai/) rather than Redis: this project's stack has no Redis
 * instance configured anywhere (confirmed — only Postgres), and the data
 * volume/query cost measured in Tahap 4 (single-digit milliseconds even
 * uncached) means a simple file cache is more than sufficient; introducing
 * a new infrastructure dependency for a sub-millisecond win would be scope
 * creep, not a performance fix.
 */
final class AiQueryCache
{
    private string $dir;

    public function __construct(?string $dir = null, private readonly int $ttlSeconds = 300)
    {
        $this->dir = $dir ?? dirname(__DIR__, 3) . '/storage/ai/cache';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    public function key(array $scope, array $semanticPlanForHash): string
    {
        $scopeHash = hash('sha256', json_encode($scope, JSON_UNESCAPED_UNICODE));
        $queryHash = hash('sha256', json_encode($semanticPlanForHash, JSON_UNESCAPED_UNICODE));
        return 'ai_' . $scopeHash . '_' . $queryHash;
    }

    public function get(string $key): mixed
    {
        $path = $this->path($key);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;
        if (!is_array($decoded) || !isset($decoded['expires_at'], $decoded['value'])) {
            return null;
        }
        if ($decoded['expires_at'] < time()) {
            @unlink($path);
            return null;
        }
        return $decoded['value'];
    }

    public function set(string $key, mixed $value): void
    {
        $payload = json_encode(['expires_at' => time() + $this->ttlSeconds, 'value' => $value], JSON_UNESCAPED_UNICODE);
        file_put_contents($this->path($key), $payload, LOCK_EX);
    }

    private function path(string $key): string
    {
        // $key is always our own sha256-derived string (never raw user
        // input), but the safe basename check stays as a hard guarantee.
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        return $this->dir . '/' . $safe . '.json';
    }
}
