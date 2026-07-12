<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use Illuminate\Support\Facades\Http;

class OpenAITextGenerator implements TextGeneratorInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gpt-4',
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('OpenAI API key not configured. Set OPENAI_API_KEY in your .env file.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->retry(2, 500, throw: false)->post('https://api.openai.com/v1/chat/completions', [
            'model' => $options['model'] ?? $this->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $options['system'] ?? 'Eres un experto en jardinería y plantas que crea contenido educativo y atractivo.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI API error: {$response->body()}");
        }

        $content = $response->json('choices.0.message.content');

        // A refusal, an empty `choices` array, or a reasoning response with no
        // message content yields null here — the method is typed `: string`, so
        // returning it would throw a TypeError and abort the whole daily run.
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('OpenAI returned an empty or non-text completion.');
        }

        return $content;
    }

    public function getProviderName(): string
    {
        return 'OpenAI';
    }
}
