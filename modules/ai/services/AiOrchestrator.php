<?php

declare(strict_types=1);

namespace Forsa\Ai\Services;

use Forsa\Ai\Providers\AiProviderException;
use Forsa\Ai\Providers\AiProviderInterface;
use Forsa\Ai\Repositories\AiToolExecutionRepository;
use Forsa\Ai\Semantic\SemanticException;
use Forsa\Ai\Tools\QueryForsaTool;
use Forsa\Ai\Tools\ToolRegistry;

/**
 * PRD §23 — the one place that turns (conversation history + new user
 * message) into an LLM call and back, now including the tool-calling round
 * trip (PRD §1 architecture: LLM -> Tool Calling -> Validator/Authorization
 * -> Semantic Layer -> Query Planner/Builder -> DB -> Structured Result ->
 * LLM again -> natural-language answer).
 *
 * Only the FINAL natural-language reply is persisted to ai_messages (kept
 * identical to Phase 2's simple history replay, which only ever stores
 * {role, content} pairs) — the intermediate tool-call/tool-result exchange
 * is NOT replayed into later turns' history, only logged for audit into
 * forsa_ai_tool_executions. This is a deliberate scope choice: correctly
 * replaying OpenAI's exact tool_calls message shape from a generic
 * {role, content} history row on every subsequent turn is real complexity
 * with no PRD-mandated behavior riding on it (a past turn's final answer
 * already carries the information a follow-up question needs).
 */
final class AiOrchestrator
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        Anda adalah FORSA AI Assistant, asisten untuk membaca data Formasi Tenaga Kerja (FTK) dan realisasi Subholding/Anak Perusahaan PLN.

        Aturan penting:
        - Anda punya satu tool, query_forsa, untuk mengambil data FTK/realisasi yang sudah divalidasi dan tersimpan di database FORSA.
        - SETIAP kali pengguna menanyakan angka spesifik (total FTK, realisasi, gap, persentase pemenuhan, perbandingan, ranking, dsb), WAJIB panggil query_forsa terlebih dahulu. Jangan pernah menjawab dengan angka hasil karangan/tebakan sendiri.
        - Jika query_forsa tidak menemukan data yang sesuai, sampaikan apa adanya (tidak ditemukan / belum tersedia), jangan mengarang angka pengganti.
        - Jika pertanyaan tentang periode ambigu (mis. "data bulan lalu" tanpa konteks SH/AP atau periode yang jelas, dan ada beberapa periode aktif yang mungkin dimaksud), JANGAN langsung menganggap periode terbaru. Tanyakan dulu ke pengguna periode mana yang dimaksud sebelum memanggil query_forsa.
        - Jawaban singkat, ramah, dan berbahasa Indonesia. Jangan menampilkan SQL atau detail teknis ke pengguna.
        PROMPT;

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly AiConversationService $conversations,
        private readonly ToolRegistry $tools = new ToolRegistry(),
        private readonly QueryForsaTool $queryForsaTool = new QueryForsaTool(),
        private readonly AiToolExecutionRepository $toolExecutions = new AiToolExecutionRepository(),
    ) {
        if (!$this->tools->has(QueryForsaTool::NAME)) {
            $schema = QueryForsaTool::schema();
            $this->tools->register($schema['name'], $schema['description'], $schema['parameters']);
        }
    }

    public function reply(array $conversation, string $userMessage, array $user): string
    {
        $conversationId = (int) $conversation['id'];
        $this->conversations->recordUserMessage($conversation, $userMessage);

        $history = $this->conversations->history($conversationId);
        $messages = [['role' => 'system', 'content' => self::SYSTEM_PROMPT]];
        foreach ($history as $row) {
            $messages[] = ['role' => $row['role'], 'content' => $row['content']];
        }

        $response = $this->provider->chat($messages, $this->tools->all());

        if ($response->hasToolCalls()) {
            $messages[] = [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => array_map(
                    static fn (array $tc) => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => ['name' => $tc['name'], 'arguments' => $tc['arguments']],
                    ],
                    $response->toolCalls
                ),
            ];

            foreach ($response->toolCalls as $call) {
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'],
                    'content' => $this->runTool($conversationId, $call, $user),
                ];
            }

            // Second round-trip: model now has the structured tool result
            // and composes the natural-language answer (PRD §12.6).
            $response = $this->provider->chat($messages, $this->tools->all());
        }

        $reply = $response->content ?? 'Maaf, saya belum bisa memberikan jawaban untuk pertanyaan itu.';
        $this->conversations->recordAssistantMessage($conversationId, $reply);

        return $reply;
    }

    private function runTool(int $conversationId, array $call, array $user): string
    {
        $start = microtime(true);
        $args = json_decode($call['arguments'], true) ?? [];

        $userId = (int) $user['id'];

        if ($call['name'] !== QueryForsaTool::NAME) {
            $this->toolExecutions->log($conversationId, $userId, $call['name'], $args, null, null, 0, 'unknown_tool');
            return json_encode(['error' => 'Tool tidak dikenal.'], JSON_UNESCAPED_UNICODE);
        }

        try {
            $result = $this->queryForsaTool->execute($args, $user);
            $elapsed = (int) ((microtime(true) - $start) * 1000);
            $this->toolExecutions->log(
                $conversationId,
                $userId,
                QueryForsaTool::NAME,
                $args,
                ['sql' => $result['sql']],
                ['row_count' => count($result['rows']), 'resolved_period' => $result['resolved_period']],
                $elapsed,
                'success'
            );

            return json_encode([
                'resolved_period' => $result['resolved_period'],
                'previous_period' => $result['previous_period'],
                'rows' => $result['rows'],
                'previous_rows' => $result['previous_rows'],
            ], JSON_UNESCAPED_UNICODE);
        } catch (SemanticException $e) {
            $elapsed = (int) ((microtime(true) - $start) * 1000);
            $this->toolExecutions->log($conversationId, $userId, QueryForsaTool::NAME, $args, null, null, $elapsed, 'error:' . $e->errorCode);
            return json_encode(['error' => $e->getMessage(), 'error_code' => $e->errorCode], JSON_UNESCAPED_UNICODE);
        }
    }
}
