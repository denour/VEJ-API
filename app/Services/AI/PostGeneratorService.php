<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use App\Models\ImageGenerationRequest;
use App\Models\Post;
use Illuminate\Support\Str;

class PostGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
        private readonly AuthorDescriptionGeneratorService $authorDescriptionService,
    ) {}

    /**
     * Generate a complete post with AI-generated content.
     */
    public function generatePost(Author $author, ?string $topic = null, array $options = []): Post
    {
        $data = $this->generatePostData($author, $topic, $options);

        $post = Post::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']),
            'excerpt' => $data['excerpt'],
            'content' => $data['content'],
            'list' => $data['list'],
            'category' => $data['category'],
            'tags' => $data['tags'],
            'author_id' => $author->id,
            'status' => 'draft',
            'featured' => false,
            'reading_time' => $data['reading_time'],
        ]);

        $this->generateImagesForPost($post, $data['structure']);

        return $post;
    }

    /**
     * Generate post data without creating a database record.
     * Returns an array with all the generated content.
     *
     * @return array{title: string, excerpt: string, content: array, list: array, category: string, tags: array, reading_time: int, structure: array}
     */
    public function generatePostData(Author $author, ?string $topic = null, array $options = []): array
    {
        $authorAttributes = $this->extractAuthorAttributes($author);
        $structure = $this->generatePostStructure($author, $authorAttributes, $topic, $options);
        $contentBlocks = $this->generateContentBlocks($structure['blocks'], $authorAttributes);
        $tableOfContents = $this->generateTableOfContents($contentBlocks);

        return [
            'title' => $structure['title'],
            'excerpt' => $structure['excerpt'],
            'content' => $contentBlocks,
            'list' => $tableOfContents,
            'category' => $structure['category'],
            'tags' => $structure['tags'],
            'reading_time' => $this->estimateReadingTime($contentBlocks),
            'structure' => $structure,
        ];
    }

    /**
     * Generate images for an existing post.
     * This includes the cover image and any content block images.
     */
    public function generateImagesForPost(Post $post, ?array $structure = null): void
    {
        $title = $post->title;
        $excerpt = $post->excerpt ?? '';

        $this->generateCoverImage($post, $title, $excerpt);

        if ($structure && ! empty($structure['blocks'])) {
            $this->generateContentImages($post, $structure['blocks']);
        } elseif (! empty($post->content) && is_array($post->content)) {
            // If no structure provided, use content blocks to find image blocks
            $this->generateContentImagesFromContent($post);
        }
    }

    /**
     * Generate images for content blocks that already exist in the post.
     */
    private function generateContentImagesFromContent(Post $post): void
    {
        foreach ($post->content as $index => $block) {
            if (($block['type'] ?? null) === 'image' && empty($block['data']['url'] ?? null)) {
                $attribute = "content.{$index}.data.url";

                // Skip if there's already a pending request for this attribute
                if (ImageGenerationRequest::hasPendingRequest(Post::class, $post->id, $attribute)) {
                    continue;
                }

                $description = $block['data']['alt'] ?? $block['data']['caption'] ?? 'Beautiful garden plant';
                $imagePrompt = $this->generateImagePrompt($description);

                try {
                    $taskId = $this->imageGenerator->generate($imagePrompt, [
                        'aspectRatio' => '16:9',
                        'resolution' => '2K',
                        'imageUrls' => [''],
                        'callBackUrl' => url('api/webhooks/banana'),
                    ]);

                    ImageGenerationRequest::query()->create([
                        'external_id' => $taskId,
                        'post_id' => $post->id,
                        'targetable_type' => Post::class,
                        'targetable_id' => $post->id,
                        'prompt' => $imagePrompt,
                        'status' => 'pending',
                        'metadata' => [
                            'attribute' => $attribute,
                            'model_name' => 'Post',
                            'block_index' => $index,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to generate content image', [
                        'post_id' => $post->id,
                        'block_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Extract author attributes from detailed description.
     */
    private function extractAuthorAttributes(Author $author): array
    {
        if (empty($author->detailed_description)) {
            return [
                'tone' => 'conversacional y educativo',
                'personality' => 'entusiasta',
                'writing_style' => 'claro y accesible',
                'themes' => ['jardinería', 'plantas'],
                'editorial_focus' => 'educación práctica',
            ];
        }

        return $this->authorDescriptionService->extractAttributes($author->detailed_description);
    }

    /**
     * Generate the post structure (title, blocks outline).
     */
    private function generatePostStructure(Author $author, array $authorAttributes, ?string $topic, array $options): array
    {
        $topicInstruction = $topic ? "El tema del post debe ser: {$topic}" : 'Elige un tema relevante';
        $themesString = is_array($authorAttributes['themes']) ? implode(', ', $authorAttributes['themes']) : $authorAttributes['themes'];
        $editorialFocus = is_array($authorAttributes['editorial_focus']) ? implode(', ', $authorAttributes['editorial_focus']) : $authorAttributes['editorial_focus'];

        $lengthInstruction = match ($options['length'] ?? 'medium') {
            'short' => 'El post debe ser corto (4-5 bloques)',
            'long' => 'El post debe ser largo (8-10 bloques)',
            default => 'El post debe ser de longitud media (5-8 bloques)',
        };

        $prompt = <<<PROMPT
Genera una idea para un post sobre jardinería y plantas para un blog llamado "Vida en el Jardín".
El post debe ser educativo, práctico y atractivo para aficionados a la jardinería.

IMPORTANTE - El autor tiene estas características que DEBES reflejar:
- Tono: {$authorAttributes['tone']}
- Personalidad: {$authorAttributes['personality']}
- Estilo de escritura: {$authorAttributes['writing_style']}
- Temas principales: {$themesString}
- Foco Editorial: {$editorialFocus}
{$topicInstruction}

Responde ÚNICAMENTE en formato JSON válido con la siguiente estructura:
{
    "title": "Título del post",
    "excerpt": "Breve descripción del post (2-3 oraciones)",
    "category": "Una de: Cuidado, Identificación, Decoración, Herramientas, Consejos",
    "tags": ["tag1", "tag2", "tag3"],
    "blocks": [
        {
            "type": "paragraph|heading|image|list|quote",
            "title: "titulo del bloque.",
            "description": "Descripción de qué debe contener este bloque"
        }
    ]
}

{$lengthInstruction} incluyendo:
- Al menos un heading
- Al menos 2-3 párrafos
- Al menos 1 imagen
- Al menos 3 bloques de lista
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'temperature' => 0.8,
            'max_tokens' => 1500,
        ]);

        // Limpiar la respuesta (remover markdown code blocks si existen)
        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        return json_decode(trim($response), true);
    }

    /**
     * Generate content for each block.
     */
    private function generateContentBlocks(array $blocks, array $authorAttributes): array
    {
        $contentBlocks = [];

        foreach ($blocks as $index => $block) {
            $contentBlocks[] = match ($block['type']) {
                'heading' => $this->generateHeading($block['description']),
                'image' => $this->generateImageBlock($block['description']),
                //                'list' => $this->generateList($block['description'], $authorAttributes),
                'quote' => $this->generateQuote($block['description']),
                default => $this->generateParagraph($block['description'], $authorAttributes),
            };
        }

        return $contentBlocks;
    }

    private function generateParagraph(string $description, array $authorAttributes): array
    {
        $editorialFocus = is_array($authorAttributes['editorial_focus']) ? implode(', ', $authorAttributes['editorial_focus']) : $authorAttributes['editorial_focus'];
        $prompt = <<<PROMPT
Genera un párrafo para un blog de jardinería basado en esta descripción:
{$description}

IMPORTANTE - Escribe con estas características del autor:
- Tono: {$authorAttributes['tone']}
- Personalidad: {$authorAttributes['personality']}
- Foco Editorial: {$editorialFocus}
- Estilo de escritura: {$authorAttributes['writing_style']}

El párrafo debe ser:
- Informativo y práctico
- Entre 80-150 palabras
- En español
- Sin formato markdown, solo texto plano
- Debe reflejar el tono y personalidad del autor

Responde ÚNICAMENTE con el texto del párrafo, sin etiquetas ni formato adicional.
PROMPT;

        $text = $this->textGenerator->generate($prompt, [
            'max_tokens' => 300,
        ]);

        return [
            'type' => 'paragraph',
            'data' => ['text' => trim($text)],
        ];
    }

    private function generateHeading(string $description): array
    {
        $prompt = <<<PROMPT
Generate a short, descriptive section heading (maximum 8 words) in Spanish for a gardening blog based on this description: {$description}

The heading must:
- Be highly SEO-optimized using natural, relevant keywords
- Be catchy, engaging, and original
- Sound clear and appealing to plant enthusiasts
- Avoid punctuation and formatting

Respond ONLY with the final title text.
PROMPT;

        $text = $this->textGenerator->generate($prompt, [
            'max_tokens' => 50,
        ]);

        return [
            'type' => 'heading',
            'data' => ['text' => trim($text), 'level' => 2],
        ];
    }

    private function generateImageBlock(string $description): array
    {
        // Return image block structure without URL - it will be populated by webhook later
        return [
            'type' => 'image',
            'data' => [
                'url' => null,
                'alt' => $description,
                'caption' => $description,
            ],
        ];
    }

    private function generateImagePrompt(string $description): string
    {
        $prompt = <<<PROMPT
You are an image-prompt generator.

TASK:
Generate ONE single image-generation prompt in English, based on the following gardening blog description:

{$description}

RULES:
- Output ONLY the final image prompt.
- Do NOT include explanations, titles, headings, variants, bullet points, or commentary.
- Do NOT mention tools, platforms, or engines.
- Do NOT add negative prompts unless explicitly asked.
- Write in a single paragraph.
- The result must be directly usable in an image generation model.
- No Text, no watermark, no formatting.

IMAGE STYLE REQUIREMENTS:
Photorealistic image of plants or gardening with:
lush tropical foliage, natural imperfections, cinematic warm color grading,
subtle film grain, soft diffused golden-hour light filtering through leaves,
balanced immersive composition centered on the main plant,
surrounding complementary greenery and shallow depth of field.
PROMPT;

        return $this->textGenerator->generate($prompt, [
            'max_tokens' => 200,
        ]);
    }

    private function generateList(string $description, array $authorAttributes): array
    {
        $prompt = <<<PROMPT
Genera una lista para un blog de jardinería basado en esta descripción:
{$description}

IMPORTANTE - Escribe con estas características del autor:
- Tono: {$authorAttributes['tone']}
- Personalidad: {$authorAttributes['personality']}

La lista debe tener entre 4-7 items.
Cada item debe ser:
- Conciso (1-2 oraciones)
- Práctico y útil
- En español
- Debe reflejar el tono y personalidad del autor

Responde en formato JSON:
{
    "items": ["item 1", "item 2", "item 3"]
}
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'max_tokens' => 400,
        ]);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $data = json_decode(trim($response), true);

        return [
            'type' => 'list',
            'data' => [
                'items' => $data['items'] ?? [],
                'style' => 'unordered',
            ],
        ];
    }

    private function generateQuote(string $description): array
    {
        $prompt = <<<PROMPT
Genera una cita inspiradora relacionada con jardinería y plantas basada en:
{$description}

La cita debe ser:
- Inspiradora y memorable
- Entre 15-30 palabras
- En español
- Puede ser de un autor real o creada

Responde en formato JSON:
{
    "text": "La cita",
    "author": "Autor (puede ser 'Proverbio popular' o un nombre real)"
}
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'max_tokens' => 150,
        ]);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $data = json_decode(trim($response), true);

        return [
            'type' => 'quote',
            'data' => [
                'text' => $data['text'] ?? '',
                'author' => $data['author'] ?? 'Anónimo',
            ],
        ];
    }

    /**
     * Generate images for content blocks that have type 'image'.
     */
    private function generateContentImages(Post $post, array $blocks): void
    {
        foreach ($blocks as $index => $block) {
            if ($block['type'] === 'image') {
                $attribute = "content.{$index}.data.url";

                // Skip if there's already a pending request for this attribute
                if (ImageGenerationRequest::hasPendingRequest(Post::class, $post->id, $attribute)) {
                    continue;
                }

                $imagePrompt = $this->generateImagePrompt($block['description']);

                try {
                    $taskId = $this->imageGenerator->generate($imagePrompt, [
                        'aspectRatio' => '16:9',
                        'resolution' => '2K',
                        'imageUrls' => [''],
                        'callBackUrl' => url('api/webhooks/banana'),
                    ]);

                    ImageGenerationRequest::query()->create([
                        'external_id' => $taskId,
                        'post_id' => $post->id,
                        'targetable_type' => Post::class,
                        'targetable_id' => $post->id,
                        'prompt' => $imagePrompt,
                        'status' => 'pending',
                        'metadata' => [
                            'attribute' => $attribute,
                            'model_name' => 'Post',
                            'block_index' => $index,
                        ],
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to generate content image', [
                        'post_id' => $post->id,
                        'block_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Generate cover image for the post.
     */
    private function generateCoverImage(Post $post, string $title, string $excerpt): void
    {
        // Skip if post already has a cover image or there's a pending request
        if (! empty($post->cover_image)) {
            return;
        }

        if (ImageGenerationRequest::hasPendingRequest(Post::class, $post->id, 'cover_image')) {
            return;
        }

        $prompt = <<<PROMPT
Create a captivating hero image for a gardening blog post.
Theme inspiration: {$excerpt}
Requirements:
- Eye-catching, professional hero image (no text, no typography, no logos, no watermarks)
- High-quality, photorealistic style with lush plants and vibrant greenery
- Warm, inviting color palette with natural lighting
- Cinematic composition with depth and visual interest
- Works well as a banner/cover image
- No text, no watermark
PROMPT;

        try {
            $taskId = $this->imageGenerator->generate($prompt, [
                'aspectRatio' => '16:9',
                'resolution' => '2K',
                'imageUrls' => [''],
                'callBackUrl' => url('api/webhooks/banana'),
            ]);

            ImageGenerationRequest::query()->create([
                'external_id' => $taskId,
                'post_id' => $post->id,
                'targetable_type' => Post::class,
                'targetable_id' => $post->id,
                'prompt' => $prompt,
                'status' => 'pending',
                'metadata' => [
                    'attribute' => 'cover_image',
                    'model_name' => 'Post',
                ],
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate post cover image', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate table of contents from headings in content blocks.
     */
    private function generateTableOfContents(array $contentBlocks): array
    {
        $toc = [];

        foreach ($contentBlocks as $index => $block) {
            if ($block['type'] === 'heading') {
                $toc[] = [
                    'id' => 'section-'.$index,
                    'text' => $block['data']['text'],
                ];
            }
        }

        return $toc;
    }

    /**
     * Estimate reading time based on content.
     */
    private function estimateReadingTime(array $contentBlocks): int
    {
        $wordCount = 0;

        foreach ($contentBlocks as $block) {
            $text = match ($block['type']) {
                'paragraph' => $block['data']['text'] ?? '',
                'heading' => $block['data']['text'] ?? '',
                'list' => implode(' ', $block['data']['items'] ?? []),
                'quote' => $block['data']['text'] ?? '',
                default => '',
            };

            $wordCount += str_word_count($text);
        }

        // Average reading speed: 200 words per minute
        return max(1, (int) ceil($wordCount / 200));
    }
}
