<?php

declare(strict_types=1);

namespace Forsa\Ai\Tools;

use Forsa\Ai\Semantic\SemanticException;

/**
 * Structural validation of the raw JSON arguments the LLM sent, before
 * QueryPlanner ever touches them — rejects malformed shapes early with a
 * clear error instead of a confusing downstream failure.
 */
final class ToolValidator
{
    public function validateQueryForsaArgs(array $args): void
    {
        if (!isset($args['metrics']) || !is_array($args['metrics']) || $args['metrics'] === []) {
            throw new SemanticException('Argumen "metrics" wajib diisi.', SemanticException::METRIC_UNAVAILABLE);
        }
        foreach (['dimensions', 'filters', 'sort'] as $key) {
            if (isset($args[$key]) && !is_array($args[$key])) {
                throw new SemanticException("Argumen \"{$key}\" harus berupa array.", SemanticException::METRIC_UNAVAILABLE);
            }
        }
        if (isset($args['limit']) && (!is_int($args['limit']) && !is_numeric($args['limit']))) {
            throw new SemanticException('Argumen "limit" harus berupa angka.', SemanticException::METRIC_UNAVAILABLE);
        }
    }
}
