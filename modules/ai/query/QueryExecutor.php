<?php

declare(strict_types=1);

namespace Forsa\Ai\Query;

use Forsa\Ai\Semantic\SemanticException;
use PDOException;

/**
 * PRD §12.4/§15.1 — runs the parameterized SQL QueryBuilder produced and
 * nothing else (no string building here). Connects as the dedicated
 * `forsa_ai_reader` role (see AiReaderConnection, PRD §4.3) rather than the
 * app's own DB_USERNAME — defense-in-depth: even a bug that let an
 * unapproved identifier through QueryBuilder would still fail at the
 * database with "permission denied", not silently succeed.
 */
final class QueryExecutor
{
    public function __construct(private readonly int $timeoutMs = 5000)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function run(string $sql, array $params): array
    {
        $pdo = AiReaderConnection::connection();
        // Plain SET (not SET LOCAL, which is a same-statement no-op outside
        // an explicit transaction block) — reset in `finally` so it never
        // leaks a 5s cap onto unrelated queries later in the same request.
        $pdo->exec('SET statement_timeout = ' . (int) $this->timeoutMs);

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'statement timeout')) {
                throw new SemanticException(
                    'Permintaan membutuhkan pemrosesan data yang terlalu besar. Silakan persempit periode atau organisasi.',
                    SemanticException::TIMEOUT
                );
            }
            throw $e;
        } finally {
            $pdo->exec('SET statement_timeout = 0');
        }
    }
}
