<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use App\Models\ImageGenerationRequest;

class AuthorGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {}

    public function generate(string $name): Author
    {
        $content = $this->generateDescriptions($name);

        // Create author first
        $author = Author::create([
            'name' => $name,
            'description' => $content['description'] ?? null,
            'detailed_description' => $content['detailed_description'] ?? null,
        ]);

        // Generate avatar after creating the author
        $this->generateAvatar($author);

        return $author;
    }

    private function generateDescriptions(string $name): array
    {
        $prompt = <<<PROMPT
Eres un editor de un blog de jardinería llamado "Vida en el Jardín".
Genera descripciones para el autor con nombre: {$name}

Responde SOLO con JSON válido usando esta estructura:
{
  "description": "Resumen corto (1-2 frases) en español",
  "detailed_description": "Perfil más completo (5-8 frases) en español, tono cercano y experto en jardinería"
}
PROMPT;

        $raw = $this->textGenerator->generate($prompt, [
            'system' => 'Eres un experto en redacción para blogs. Devuelve solo JSON válido.',
            'max_tokens' => 350,
        ]);

        $candidate = trim($raw);
        if (preg_match('/```(?:json)?\n(.+?)\n```/is', $candidate, $m)) {
            $candidate = $m[1];
        }
        $data = json_decode($candidate, true);

        return is_array($data) ? $data : [];
    }

    private function generateAvatar(Author $author): void
    {
        $prompt = <<<PROMPT
Professional studio headshot of a gardening expert named {$author->name}.
Style: clean portrait, soft diffused lighting, subtle smile, shallow depth of field, neutral background.
No text, no watermark, high detail, natural look.
PROMPT;

        try {
            $taskId = $this->imageGenerator->generate($prompt, [
                'aspectRatio' => '1:1',
                'resolution' => '2K',
                'imageUrls' => [''],
                'callBackUrl' => url('api/webhooks/banana'),
            ]);

            // Save taskId in ImageGenerationRequest
            ImageGenerationRequest::query()->create([
                'external_id' => $taskId,
                'targetable_type' => Author::class,
                'targetable_id' => $author->id,
                'prompt' => $prompt,
                'status' => 'pending',
                'metadata' => [
                    'attribute' => 'image',
                    'model_name' => 'Author',
                ],
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail author creation
            \Illuminate\Support\Facades\Log::error('Failed to generate author avatar', [
                'author_id' => $author->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
