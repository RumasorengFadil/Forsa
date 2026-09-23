<?php

declare(strict_types=1);

namespace Forsa\Ai\Tools;

/**
 * PRD §11/§41 — holds every tool schema the LLM is allowed to call.
 * Deliberately empty until Phase 6 (Analytics Tool): registering `query_forsa`
 * here before the Phase 3 gate (user choosing which tables/views the AI may
 * read) and the semantic layer it depends on (Phase 4-5) would let the LLM
 * request data that no source has been approved for yet — see PRD §5
 * "Mandatory AI Setup Gate". AiOrchestrator asks this registry for the tool
 * list on every request; today that list is always empty, so the LLM is
 * called with zero tools and can only ever reply in plain text.
 */
final class ToolRegistry
{
    /** @var array<string, array{name: string, description: string, parameters: array}> */
    private array $tools = [];

    public function register(string $name, string $description, array $parameters): void
    {
        $this->tools[$name] = ['name' => $name, 'description' => $description, 'parameters' => $parameters];
    }

    /** @return array<int, array{name: string, description: string, parameters: array}> */
    public function all(): array
    {
        return array_values($this->tools);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
