<?php

declare(strict_types=1);

require __DIR__ . '/../../../shared/bootstrap.php';
$currentUser = require_login();
csrf_require();

use Forsa\Ai\Providers\AiProviderException;
use Forsa\Ai\Providers\OpenAiProvider;
use Forsa\Ai\Security\RateLimiter;
use Forsa\Ai\Services\AiConversationService;
use Forsa\Ai\Services\AiOrchestrator;

$body = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($body)) {
    json_error('Body permintaan tidak valid.', 400);
}

$message = trim((string) ($body['message'] ?? ''));
$conversationId = isset($body['conversation_id']) ? (int) $body['conversation_id'] : null;

if ($message === '') {
    json_error('Pesan tidak boleh kosong.', 422);
}
if (mb_strlen($message) > 2000) {
    json_error('Pesan terlalu panjang (maksimum 2000 karakter).', 422);
}

$rateLimiter = new RateLimiter();
if ($rateLimiter->tooManyRequests((int) $currentUser['id'])) {
    json_error('Terlalu banyak permintaan dalam waktu singkat. Silakan tunggu sebentar lalu coba lagi.', 429);
}

$conversationService = new AiConversationService();
$conversation = $conversationService->resolveConversation((int) $currentUser['id'], $conversationId);

$aiConfig = require __DIR__ . '/../../../config/ai.php';

try {
    $provider = new OpenAiProvider(
        apiKey: $aiConfig['api_key'],
        model: $aiConfig['model'],
        baseUrl: $aiConfig['base_url'],
        timeoutSeconds: $aiConfig['timeout_seconds'],
    );
    $orchestrator = new AiOrchestrator($provider, $conversationService, $aiConfig['model']);
    $result = $orchestrator->reply($conversation, $message, $currentUser);
} catch (AiProviderException $e) {
    error_log('[ai_chat_api] ' . $e->getMessage());
    json_error('FORSA AI Assistant sedang tidak dapat dihubungi. Silakan coba lagi sebentar lagi.', 502);
}

json_success([
    'conversation_id' => (int) $conversation['id'],
    'reply' => $result['reply'],
    'execution_time_ms' => $result['execution_time_ms'],
    'model' => $result['model'],
    'source' => $result['source'],
]);
