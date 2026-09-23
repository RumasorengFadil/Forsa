<?php

declare(strict_types=1);

require __DIR__ . '/../../../shared/bootstrap.php';
$currentUser = require_login();

use Forsa\Ai\Services\AiConversationService;

$conversationService = new AiConversationService();

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
if ($id !== null) {
    $conversation = $conversationService->find((int) $currentUser['id'], $id);
    if ($conversation === null) {
        json_error('Percakapan tidak ditemukan.', 404);
    }
    json_success([
        'conversation' => $conversation,
        'messages' => $conversationService->history($id),
    ]);
}

json_success(['conversations' => $conversationService->listForUser((int) $currentUser['id'])]);
