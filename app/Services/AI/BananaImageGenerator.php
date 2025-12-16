<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

        $isPro = app()->isProduction();

        $payload = [
            'prompt' => $prompt,
            // Correct type per API docs
            'type' => 'TEXTTOIMAGE',
            'image_size' => $size,
        ];

        // Some Banana/NanoBanana deployments (production variant) require additional fields
        // like imageUrls, resolution, callBackUrl, and aspectRatio. Provide them when
        // targeting the pro endpoint to maintain compatibility with that version.
        if ($isPro) {
            $payload['imageUrls'] = $options['imageUrls'] ?? [];

            // Default to '2K' unless explicitly provided via options.
            $payload['resolution'] = $options['resolution'] ?? '2K';

            // Use configured callback URL if available; keep polling logic regardless.
            $callback = config('services.banana.callback_url');
            if (! empty($options['callBackUrl'])) {
                $callback = (string) $options['callBackUrl'];
            }
            if (! empty($callback)) {
                $payload['callBackUrl'] = $callback;
            }

            // Derive a simple aspect ratio if not provided.
            if (! empty($options['aspectRatio'])) {
                $payload['aspectRatio'] = (string) $options['aspectRatio'];
            } else {
                // Map based on width/height intent
                $width = $options['width'] ?? 1024;
                $height = $options['height'] ?? 1024;
                if ($width > $height) {
                    $payload['aspectRatio'] = '16:9';
                } elseif ($height > $width) {
                    $payload['aspectRatio'] = '9:16';
                } else {
                    $payload['aspectRatio'] = '1:1';
                }
            }
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type' => 'application/json',
        ])->timeout(30)->post(
            'https://api.nanobananaapi.ai/api/v1/nanobanana/'.($isPro ? 'generate-pro' : 'generate'),
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
                $filename = 'posts/'.uniqid('', true).'.png';

                Storage::disk('s3')->put($filename, $imageContent, ['visibility' => 'public']);

                return Storage::disk('s3')->url($filename);
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
