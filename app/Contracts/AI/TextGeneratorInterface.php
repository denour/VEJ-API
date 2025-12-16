<?php

namespace App\Contracts\AI;

interface TextGeneratorInterface
{
    /**
     * Generate text content based on a prompt.
     */
    public function generate(string $prompt, array $options = []): string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
