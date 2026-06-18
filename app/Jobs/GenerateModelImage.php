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
        // Skip if there's already a pending request for this attribute
        if (ImageGenerationRequest::hasPendingRequest(
            get_class($this->model),
            $this->model->getKey(),
            $this->attribute
        )) {
            Log::info('Skipping image generation - pending request exists', [
                'model' => get_class($this->model),
                'id' => $this->model->getKey(),
                'attribute' => $this->attribute,
            ]);

            return;
        }

        try {
            $finalPrompt = $this->prompt ?? $this->generatePrompt();

            $options = [
                'aspectRatio' => '16:9',
                'resolution' => '2K',
                'directory' => $this->getStorageDirectory(),
            ];

            if (! $generator->isSynchronous()) {
                $options['callBackUrl'] = url('api/webhooks/banana');
                $options['imageUrls'] = [''];
            }

            $response = $generator->generate($finalPrompt, $options);

            if ($generator->isSynchronous()) {
                // Sync providers (OpenAI) return the final S3 URL directly
                $this->model->{$this->attribute} = $response;
                $this->model->save();

                ImageGenerationRequest::query()->create([
                    'external_id' => null,
                    'targetable_type' => get_class($this->model),
                    'targetable_id' => $this->model->getKey(),
                    'prompt' => $finalPrompt,
                    'status' => 'completed',
                    'image_url' => $response,
                    'metadata' => [
                        'attribute' => $this->attribute,
                        'model_name' => $this->getModelName(),
                        'provider' => $generator->getProviderName(),
                    ],
                ]);

                Log::info('Image generated synchronously', [
                    'model' => get_class($this->model),
                    'id' => $this->model->getKey(),
                    'url' => $response,
                ]);
            } else {
                // Async providers (Banana) return a taskId for polling
                ImageGenerationRequest::query()->create([
                    'external_id' => $response,
                    'targetable_type' => get_class($this->model),
                    'targetable_id' => $this->model->getKey(),
                    'prompt' => $finalPrompt,
                    'status' => 'pending',
                    'metadata' => [
                        'attribute' => $this->attribute,
                        'model_name' => $this->getModelName(),
                        'provider' => $generator->getProviderName(),
                    ],
                ]);

                Log::info('Image generation request created (async)', [
                    'model' => get_class($this->model),
                    'id' => $this->model->getKey(),
                    'task_id' => $response,
                ]);
            }
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

        return "Create a captivating, photorealistic hero PHOTOGRAPH for a gardening blog. Visual theme (use ONLY as inspiration for the scene — never render this text, or any words, in the image): {$title}. {$excerpt}. Requirements: a real-looking photograph of plants, gardens or greenery as if shot with a professional camera; lush, vibrant foliage; warm, inviting natural light; cinematic composition with depth and shallow depth of field; the natural scene must fill the entire frame, edge to edge. Absolutely NO text, NO words, NO letters, NO numbers, NO captions, NO titles, NO typography, NO logos, NO watermarks, NO icons, NO badges, NO labels, NO color side-panels or borders. This is NOT an infographic, NOT a poster, NOT a banner, NOT a flyer, NOT a graphic-design layout — only a clean, natural photograph.";
    }

    private function generateAuthorPrompt(): string
    {
        $name = $this->model->name;

        return "Ultra-realistic professional photographic portrait headshot of a real human gardening and plant expert named {$name} (use the name ONLY to infer a fitting, natural appearance — NEVER write the name, or any text, in the image). Shot on a DSLR with an 85mm lens, true-to-life skin texture and fine detail, natural soft studio lighting, shallow depth of field, neutral softly blurred background, candid friendly expression. It must look like an authentic photograph of a real person — NOT a 3D render, NOT CGI, NOT an illustration, NOT a painting, NOT a poster, NOT stylized. Absolutely NO text, NO words, NO letters, NO name captions, NO titles, NO logos, NO watermarks, NO borders or graphic overlays.";
    }

    private function getModelName(): string
    {
        $class = get_class($this->model);
        $parts = explode('\\', $class);

        return end($parts);
    }

    private function getStorageDirectory(): string
    {
        return match (get_class($this->model)) {
            \App\Models\Post::class => 'posts',
            \App\Models\PostBlock::class => 'posts/blocks',
            \App\Models\Author::class => 'authors',
            \App\Models\Product::class => 'products',
            \App\Models\Species::class => 'species',
            default => 'misc',
        };
    }
}
