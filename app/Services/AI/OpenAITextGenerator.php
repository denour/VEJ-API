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
        ])->timeout(120)->post('https://api.openai.com/v1/chat/completions', [
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

        return $response->json('choices.0.message.content');
    }

    public function getProviderName(): string
    {
        return 'OpenAI';
    }
}
