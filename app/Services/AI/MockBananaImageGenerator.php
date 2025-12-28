<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use Illuminate\Support\Str;

class MockBananaImageGenerator implements ImageGeneratorInterface
{
    /**
     * @var array<string, array{prompt: string, imageUrl: string}>
     */
    private static array $tasks = [];

    public function __construct(
        private readonly string $baseImageUrl = 'https://tempfile.aiquickdraw.com/h/',
    ) {}

    public function generate(string $prompt, array $options = []): string
    {
        $taskId = 'task_'.Str::random(32);
        $imageUrl = $this->baseImageUrl.$taskId.'_'.time().'.png';

        self::$tasks[$taskId] = [
            'prompt' => $prompt,
            'imageUrl' => $imageUrl,
        ];

        return $taskId;
    }

    public function getProviderName(): string
    {
        return 'MockBanana';
    }

    /**
     * @return array{status: string, imageUrl?: string, error?: string}
     */
    public function getTaskStatus(string $taskId): array
    {
        $task = self::$tasks[$taskId] ?? null;

        if (! $task) {
            return [
                'status' => 'not_found',
                'imageUrl' => null,
                'error' => 'Task not found',
            ];
        }

        return [
            'status' => 'success',
            'imageUrl' => $task['imageUrl'],
            'error' => null,
        ];
    }

    /**
     * Get the webhook payload that NanoBanana would send for a task.
     * Matches the real NanoBanana API response format.
     *
     * @return array{msg: string, code: int, data: array{taskId: string, info: array{resultImageUrl: string}}}
     */
    public static function getWebhookPayload(string $taskId): array
    {
        $task = self::$tasks[$taskId] ?? null;
        $imageUrl = $task['imageUrl'] ?? 'https://tempfile.aiquickdraw.com/mock.png';

        return [
            'msg' => 'Image generated successfully.',
            'code' => 200,
            'data' => [
                'taskId' => $taskId,
                'info' => [
                    'resultImageUrl' => $imageUrl,
                ],
            ],
        ];
    }

    /**
     * Clear all stored tasks (useful for test cleanup).
     */
    public static function clearTasks(): void
    {
        self::$tasks = [];
    }

    /**
     * Get all stored tasks (useful for debugging).
     *
     * @return array<string, array{prompt: string, imageUrl: string}>
     */
    public static function getTasks(): array
    {
        return self::$tasks;
    }
}
