<?php

declare(strict_types=1);

namespace Forsa\Ai\Providers;

/**
 * Value object returned by AiProviderInterface::chat(). `toolCalls` stays
 * empty until Phase 6 actually registers a tool with the provider — Phase 2
 * never passes any tool schema, so a provider has nothing to call yet.
 */
final class AiResponse
{
    /**
     * @param array<int, array{id: string, name: string, arguments: string}> $toolCalls
     */
    public function __construct(
        public readonly ?string $content,
        public readonly array $toolCalls = [],
        public readonly ?string $finishReason = null,
    ) {
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
