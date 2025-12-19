<?php

namespace App\Contracts\AI;

interface ImageGeneratorInterface
{
    /**
     * Generate an image based on a prompt and return the URL or path.
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;

    /**
     * Get the status of a task from the provider.
     *
     * @return array{status: string, imageUrl?: string, error?: string}
     */
    public function getTaskStatus(string $taskId): array;
}
