<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;

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

        // Always use the Pro endpoint and payload format, regardless of environment.
        // Keep the payload minimal as requested: prompt, imageUrls, resolution, callBackUrl, aspectRatio.
        $payload = [
            'prompt' => $prompt,
            'imageUrls' => $options['imageUrls'] ?? [''],
            'resolution' => $options['resolution'] ?? '2K',
            'callBackUrl' => url('api/webhooks/banana'),
            'aspectRatio' => $options['aspectRatio'] ?? '16:9',
        ];
        \Log::error($payload);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(60)->post(
            'https://api.nanobananaapi.ai/api/v1/nanobanana/generate-pro',
            $payload,
        );

        if (! $response->successful()) {
            throw new \RuntimeException("Banana API error: {$response->body()}");
        }

        $data = $response->json();

        $taskId = $data['data']['taskId'] ?? null;

        if ($taskId === null || $taskId === '') {
            $message = 'Banana API did not return a taskId.';
            if (isset($data['message'])) {
                $message .= ' Message: '.$data['message'];
            }

            throw new \RuntimeException($message);
        }

        return $taskId;
    }

    public function getProviderName(): string
    {
        return 'Banana';
    }

    public function isSynchronous(): bool
    {
        return false;
    }

    /**
     * Get the status of a task from Banana API.
     *
     * @return array{status: string, imageUrl?: string, error?: string}
     */
    public function getTaskStatus(string $taskId): array
    {
        if (! $this->apiKey) {
            throw new \RuntimeException('Banana API key not configured. Set BANANA_API_KEY in your .env file.');
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->get(
            "https://api.nanobananaapi.ai/api/v1/nanobanana/record-info?taskId={$taskId}"
        );

        if (! $response->successful()) {
            throw new \RuntimeException("Banana API error: {$response->body()}");
        }

        $data = $response->json();

        // Extract status and image URL from response
        $status = $data['msg'] ?? $data['msg'] ?? 'unknown';
        $imageUrl = $data['data']['response']['resultImageUrl'] ?? $data['resultImageUrl'] ?? null;
        $error = $data['data']['error'] ?? $data['error'] ?? null;

        return [
            'status' => $status,
            'imageUrl' => $imageUrl,
            'error' => $error,
        ];
    }
}
