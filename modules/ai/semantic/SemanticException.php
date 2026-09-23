<?php

declare(strict_types=1);

namespace Forsa\Ai\Semantic;

/**
 * Thrown for anything the semantic layer rejects: unknown metric/dimension,
 * unsupported operator, ambiguous company/period, etc. Always caught and
 * turned into one of the PRD §36 user-facing error messages — never leaked
 * as a raw exception to the chat UI.
 */
final class SemanticException extends \RuntimeException
{
    // Matches the four PRD §36 error categories exactly, so the layer that
    // talks to the user never has to guess which canned message to show.
    // Named errorCode (not `code`) — Exception already declares a non-readonly
    // int $code, and PHP forbids redeclaring it as a readonly string property.
    public const METRIC_UNAVAILABLE = 'metric_unavailable';
    public const NO_RESULT = 'no_result';
    public const ACCESS_DENIED = 'access_denied';
    public const TIMEOUT = 'timeout';

    public function __construct(string $message, public readonly string $errorCode = self::NO_RESULT)
    {
        parent::__construct($message);
    }
}
