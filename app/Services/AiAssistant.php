<?php

namespace App\Services;

use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to an OpenAI-compatible chat completions endpoint and asks the model for
 * a short answer plus, optionally, one shell command to run on a server.
 *
 * The command is never executed here: the UI shows it and the user decides.
 */
class AiAssistant
{
    public function __construct(private AiSetting $settings) {}

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{reply: string, command: string|null, server_hint: string|null}
     */
    public function ask(array $messages, ?string $context = null): array
    {
        if (! $this->settings->configured()) {
            return ['reply' => 'The assistant is not configured yet. Add an endpoint, a model and an API key in Settings → AI assistant.', 'command' => null, 'server_hint' => null];
        }

        $system = trim((string) $this->settings->system_prompt);
        $system = $system !== '' ? $system : 'You are the Dashy operations assistant. Answer briefly and concretely.';
        $system .= ' Reply with JSON only: {"reply": string, "command": string|null, "server_hint": string|null}. '
            .'Put a single shell command in "command" when one would help, otherwise null. Never invent server names; server_hint names a server the operator should pick.';

        if ($context !== null && $context !== '') {
            $system .= "\n\nInstance context:\n".$context;
        }

        try {
            $response = Http::timeout(60)
                ->withToken((string) $this->settings->api_key)
                ->acceptJson()
                ->post(rtrim((string) $this->settings->base_url, '/').'/chat/completions', [
                    'model' => $this->settings->model,
                    'temperature' => 0.2,
                    'messages' => array_merge(
                        [['role' => 'system', 'content' => $system]],
                        array_slice($messages, -12),
                    ),
                ]);

            if ($response->failed()) {
                Log::warning('AI assistant request failed', ['status' => $response->status()]);

                return ['reply' => 'The assistant endpoint returned HTTP '.$response->status().'.', 'command' => null, 'server_hint' => null];
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $parsed = $this->parse($content);

            return $parsed;
        } catch (\Throwable $e) {
            Log::warning('AI assistant request failed', ['error' => $e->getMessage()]);

            return ['reply' => 'The assistant could not be reached: '.$e->getMessage(), 'command' => null, 'server_hint' => null];
        }
    }

    /**
     * @return array{reply: string, command: string|null, server_hint: string|null}
     */
    private function parse(string $content): array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?|```$/m', '', $content) ?? $content;

        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return [
                'reply' => (string) ($decoded['reply'] ?? $decoded['answer'] ?? ''),
                'command' => filled($decoded['command'] ?? null) ? (string) $decoded['command'] : null,
                'server_hint' => filled($decoded['server_hint'] ?? null) ? (string) $decoded['server_hint'] : null,
            ];
        }

        return ['reply' => $content, 'command' => null, 'server_hint' => null];
    }
}
