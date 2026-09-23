<?php

declare(strict_types=1);

namespace Forsa\Ai\Providers;

/**
 * Talks to OpenAI's Chat Completions API. This is the only file in the
 * codebase that ever sees MODEL_API_KEY — it never leaves this class (not
 * logged, not echoed, not put in any prompt), matching PRD §4.2/§15.1.
 */
final class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly int $timeoutSeconds = 20,
    ) {
        if ($this->apiKey === '') {
            throw new AiProviderException('MODEL_API_KEY belum diisi di .env.');
        }
    }

    public function chat(array $messages, array $tools = []): AiResponse
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = array_map(
                static fn (array $t) => [
                    'type' => 'function',
                    'function' => [
                        'name' => $t['name'],
                        'description' => $t['description'],
                        'parameters' => $t['parameters'],
                    ],
                ],
                $tools
            );
        }

        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new AiProviderException('Gagal menghubungi model AI: ' . $curlError);
        }

        $decoded = json_decode($body, true);

        if ($status !== 200 || !is_array($decoded)) {
            $providerMessage = is_array($decoded) ? ($decoded['error']['message'] ?? null) : null;
            throw new AiProviderException(
                'Model AI merespons dengan status ' . $status . ($providerMessage ? ': ' . $providerMessage : '.')
            );
        }

        $choice = $decoded['choices'][0] ?? null;
        if (!is_array($choice)) {
            throw new AiProviderException('Respons model AI tidak memiliki format yang diharapkan.');
        }

        $message = $choice['message'] ?? [];
        $toolCalls = [];
        foreach ($message['tool_calls'] ?? [] as $call) {
            $toolCalls[] = [
                'id' => $call['id'] ?? '',
                'name' => $call['function']['name'] ?? '',
                'arguments' => $call['function']['arguments'] ?? '{}',
            ];
        }

        return new AiResponse(
            content: $message['content'] ?? null,
            toolCalls: $toolCalls,
            finishReason: $choice['finish_reason'] ?? null,
        );
    }
}
