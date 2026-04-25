<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OpenAIImageGenerator implements ImageGeneratorInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'gpt-image-1',
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('OpenAI API key not configured. Set OPENAI_API_KEY in your .env file.');
        }

        $model = $options['model'] ?? $this->model;
        $size = $this->getSize($options);
        $quality = $options['quality'] ?? 'auto';

        Log::info('OpenAI image generation request', [
            'model' => $model,
            'size' => $size,
            'quality' => $quality,
            'prompt_length' => strlen($prompt),
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(180)->post('https://api.openai.com/v1/images/generations', [
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
            'quality' => $quality,
            'n' => 1,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI Image API error: {$response->body()}");
        }

        $imageUrl = $response->json('data.0.url');
        $imageB64 = $response->json('data.0.b64_json');

        if ($imageB64) {
            $imageContent = base64_decode($imageB64);
        } elseif ($imageUrl) {
            $imageContent = Http::timeout(120)->get($imageUrl)->body();
        } else {
            throw new \RuntimeException('OpenAI returned no image data');
        }

        $directory = $options['directory'] ?? 'misc';
        $filename = $directory.'/'.uniqid('openai-', true).'.png';

        Storage::disk('s3')->put($filename, $imageContent, ['visibility' => 'public']);

        $publicUrl = Storage::disk('s3')->url($filename);

        Log::info('OpenAI image stored', ['url' => $publicUrl]);

        return $publicUrl;
    }

    public function getProviderName(): string
    {
        return 'OpenAI';
    }

    public function getTaskStatus(string $taskId): array
    {
        return [
            'status' => 'completed',
            'imageUrl' => null,
            'error' => 'OpenAI generates synchronously — no task polling needed',
        ];
    }

    public function isSynchronous(): bool
    {
        return true;
    }

    private function getSize(array $options): string
    {
        $aspectRatio = $options['aspectRatio'] ?? null;

        if ($aspectRatio === '16:9' || $aspectRatio === '4:3') {
            return '1536x1024';
        }

        if ($aspectRatio === '9:16' || $aspectRatio === '3:4') {
            return '1024x1536';
        }

        $width = $options['width'] ?? 1024;
        $height = $options['height'] ?? 1024;

        if ($width > $height) {
            return '1536x1024';
        }

        if ($height > $width) {
            return '1024x1536';
        }

        return '1024x1024';
    }
}
