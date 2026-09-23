<?php

declare(strict_types=1);

namespace Forsa\Ai\Providers;

/**
 * PRD_FORSA_AI_Assistant.md §21 — Model Provider Abstraction. Nothing outside
 * this file's implementations may know which LLM vendor is in use, so
 * switching providers or adding a fallback never touches AiOrchestrator.
 */
interface AiProviderInterface
{
    /**
     * @param array<int, array{role: string, content: ?string, tool_call_id?: string, name?: string}> $messages
     * @param array<int, array{name: string, description: string, parameters: array}> $tools
     */
    public function chat(array $messages, array $tools = []): AiResponse;
}
