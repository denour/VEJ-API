<?php

namespace App\Jobs;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Models\ImageGenerationRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GenerateModelImage implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Model $model,
        public string $attribute = 'image',
        public ?string $prompt = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ImageGeneratorInterface $generator): void
    {
        try {
            // Generate prompt if not provided
            $finalPrompt = $this->prompt ?? $this->generatePrompt();

            // Get callback URL for webhook
            $callbackUrl = route('api.banana.callback');

            // Generate image using Banana API
            $response = $generator->generate($finalPrompt, [
                'callBackUrl' => $callbackUrl,
                'aspectRatio' => '16:9',
                'resolution' => '2K',
                'imageUrls' => [''],
            ]);

            // The response should be a task ID since we're using async generation
            // Create a record to track this request
            ImageGenerationRequest::query()->create([
                'external_id' => $response,
                'targetable_type' => get_class($this->model),
                'targetable_id' => $this->model->getKey(),
                'prompt' => $finalPrompt,
                'status' => 'pending',
                'metadata' => [
                    'attribute' => $this->attribute,
                    'model_name' => $this->getModelName(),
                ],
            ]);

            Log::info('Image generation request created', [
                'model' => get_class($this->model),
                'id' => $this->model->getKey(),
                'task_id' => $response,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to generate image for model', [
                'model' => get_class($this->model),
                'id' => $this->model->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function generatePrompt(): string
    {
        $modelClass = get_class($this->model);

        return match ($modelClass) {
            \App\Models\Species::class => $this->generateSpeciesPrompt(),
            \App\Models\Post::class => $this->generatePostPrompt(),
            \App\Models\Author::class => $this->generateAuthorPrompt(),
            default => "Professional image for {$this->getModelName()}",
        };
    }

    private function generateSpeciesPrompt(): string
    {
        $name = $this->model->common_name ?? $this->model->scientific_name;
        $description = $this->model->description ? Str::limit($this->model->description, 100) : '';

        return "High quality photograph of {$name}, a beautiful plant species. {$description}. Professional botanical photography, natural lighting, detailed and vibrant.";
    }

    private function generatePostPrompt(): string
    {
        $title = $this->model->title;
        $excerpt = $this->model->excerpt ? Str::limit($this->model->excerpt, 100) : '';
        $category = $this->model->category ?? 'garden';

        return "Create a professional cover image for a blog post titled '{$title}' in the {$category} category. {$excerpt}. Modern, clean, and engaging design.";
    }

    private function generateAuthorPrompt(): string
    {
        $name = $this->model->name;

        return "Professional portrait style image for author {$name}, gardening and plant expert theme, modern and friendly.";
    }

    private function getModelName(): string
    {
        $class = get_class($this->model);
        $parts = explode('\\', $class);

        return end($parts);
    }
}
