<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class OpenAIImageGenerator implements ImageGeneratorInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'dall-e-3',
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('OpenAI API key not configured. Set OPENAI_API_KEY in your .env file.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/images/generations', [
            'model' => $options['model'] ?? $this->model,
            'prompt' => $prompt,
            'size' => $this->getSize($options),
            'quality' => $options['quality'] ?? 'standard', // standard or hd
            'n' => 1,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI Image API error: {$response->body()}");
        }

        // Obtener la URL de la imagen generada
        $imageUrl = $response->json('data.0.url');

        // Descargar y guardar localmente
        $imageContent = Http::get($imageUrl)->body();
        $filename = 'posts/'.uniqid('', true).'.png';

        Storage::disk('s3')->put($filename, $imageContent, ['visibility' => 'public']);

        return Storage::disk('s3')->url($filename);
    }

    public function getProviderName(): string
    {
        return 'OpenAI DALL-E';
    }

    public function getTaskStatus(string $taskId): array
    {
        // OpenAI generates images synchronously, so this method is not needed
        // Return completed status by default
        return [
            'status' => 'completed',
            'imageUrl' => null,
            'error' => 'OpenAI does not support async task status checking',
        ];
    }

    private function getSize(array $options): string
    {
        // DALL-E 3 sizes: 1024x1024, 1024x1792, 1792x1024
        $width = $options['width'] ?? 1024;
        $height = $options['height'] ?? 1024;

        if ($width > $height) {
            return '1792x1024';
        }

        if ($height > $width) {
            return '1024x1792';
        }

        return '1024x1024';
    }
}
