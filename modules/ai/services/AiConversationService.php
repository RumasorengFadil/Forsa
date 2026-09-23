<?php

declare(strict_types=1);

namespace Forsa\Ai\Services;

use Forsa\Ai\Repositories\AiConversationRepository;
use Forsa\Ai\Repositories\AiMessageRepository;

final class AiConversationService
{
    public function __construct(
        private readonly AiConversationRepository $conversations = new AiConversationRepository(),
        private readonly AiMessageRepository $messages = new AiMessageRepository(),
    ) {
    }

    /**
     * Resolves the conversation to append to: the given id if it belongs to
     * this user, otherwise a freshly created one (PRD §18.1).
     */
    public function resolveConversation(int $userId, ?int $conversationId): array
    {
        if ($conversationId !== null) {
            $existing = $this->conversations->find($conversationId, $userId);
            if ($existing !== null) {
                return $existing;
            }
        }
        return $this->conversations->create($userId, null);
    }

    /**
     * Read-only lookup (never creates) — for a plain GET, unlike
     * resolveConversation() which is allowed to create because it backs the
     * "send a message" write path.
     */
    public function find(int $userId, int $conversationId): ?array
    {
        return $this->conversations->find($conversationId, $userId);
    }

    /** @return array<int, array<string, mixed>> */
    public function history(int $conversationId): array
    {
        return $this->messages->history($conversationId);
    }

    public function recordUserMessage(array $conversation, string $content): void
    {
        $this->messages->append((int) $conversation['id'], 'user', $content);
        if ($conversation['title'] === null) {
            // First message becomes the conversation's title (truncated),
            // same idea as ChatGPT/most chat UIs — purely cosmetic for the
            // "Riwayat" list, no bearing on how the AI answers.
            $this->conversations->setTitleIfEmpty((int) $conversation['id'], mb_substr($content, 0, 80));
        }
        $this->conversations->touch((int) $conversation['id']);
    }

    public function recordAssistantMessage(int $conversationId, string $content): void
    {
        $this->messages->append($conversationId, 'assistant', $content);
        $this->conversations->touch($conversationId);
    }

    /** @return array<int, array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        return $this->conversations->listForUser($userId);
    }
}
