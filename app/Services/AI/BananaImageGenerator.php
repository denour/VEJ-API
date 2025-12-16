<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BananaImageGenerator implements ImageGeneratorInterface
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $model = 'nano-banana',
    ) {}

    private function getSize(array $options): string
    {
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

    public function generate(string $prompt, array $options = []): string
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('Banana API key not configured. Set BANANA_API_KEY in your .env file.');
        }

        $size = $this->getSize($options);
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://api.nanobananaapi.ai/api/v1/nanobanana/generate', [
            'prompt' => $prompt,
            'type' => 'TEXTTOIAMGE',
            'image_size' => $size,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Banana API error: {$response->body()}");
        }

        $data = $response->json();

        $taskId = $data['data']['taskId'] ?? null;

        return $this->waitForCompletion($taskId, $options['timeout'] ?? 120);
    }

    private function waitForCompletion(string $taskId, int $timeoutSeconds): string
    {
        $startTime = time();
        $pollInterval = 2;

        while ((time() - $startTime) < $timeoutSeconds) {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])->get("https://api.nanobananaapi.ai/api/v1/nanobanana/record-info?taskId={$taskId}");

            $data = $response->json();
            if (! $response->successful()) {
                throw new \RuntimeException("Banana API error: {$response->body()}");
            }

            if ($data['data']['successFlag'] === 1) {
                $imageUrl = $data['data']['response']['resultImageUrl'];
                $imageContent = Http::timeout(120)->get($imageUrl)->body();
                $filename = 'posts/'.uniqid().'.png';

                Storage::disk('public')->put($filename, $imageContent);

                return $filename;
            }

            sleep($pollInterval);
        }

        throw new \RuntimeException('Timeout waiting for image generation callback');
    }

    public function getProviderName(): string
    {
        return 'Banana';
    }
}
