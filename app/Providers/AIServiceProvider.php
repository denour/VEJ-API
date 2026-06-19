<?php

namespace App\Providers;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Services\AI\BananaImageGenerator;
use App\Services\AI\MockBananaImageGenerator;
use App\Services\AI\OpenAIImageGenerator;
use App\Services\AI\OpenAITextGenerator;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register Text Generator
        $this->app->singleton(TextGeneratorInterface::class, function ($app): TextGeneratorInterface {
            $provider = config('ai.text_provider', 'openai');
            $config = config("ai.text.{$provider}");

            return match ($provider) {
                'openai' => new OpenAITextGenerator(
                    apiKey: $config['api_key'],
                    model: $config['model'],
                ),
                default => throw new \InvalidArgumentException("Unknown text provider: {$provider}"),
            };
        });

        // Register Image Generator
        $this->app->singleton(ImageGeneratorInterface::class, function ($app): ImageGeneratorInterface {
            $provider = config('ai.image_provider', 'banana');
            $config = config("ai.image.{$provider}");

            return match ($provider) {
                'banana' => new BananaImageGenerator(
                    apiKey: $config['api_key'],
                    model: $config['model'],
                ),
                'openai' => new OpenAIImageGenerator(
                    apiKey: $config['api_key'],
                    model: $config['model'],
                ),
                'mock' => new MockBananaImageGenerator,
                default => throw new \InvalidArgumentException("Unknown image provider: {$provider}"),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
