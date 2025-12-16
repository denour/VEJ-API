<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
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
        // Extract author attributes from detailed description
        $authorAttributes = $this->extractAuthorAttributes($author);

        // Paso 1: Generar la idea y estructura del post
        $structure = $this->generatePostStructure($author, $authorAttributes, $topic, $options);

        // Paso 2: Generar contenido para cada bloque
        $contentBlocks = $this->generateContentBlocks($structure['blocks'], $authorAttributes);

        // Paso 3: Generar la tabla de contenido
        $tableOfContents = $this->generateTableOfContents($contentBlocks);

        // Paso 4: Generar la imagen de portada
        $coverImage = $this->generateCoverImage($structure['title'], $structure['excerpt']);

        // Paso 5: Crear el post
        return Post::create([
            'title' => $structure['title'],
            'slug' => Str::slug($structure['title']),
            'excerpt' => $structure['excerpt'],
            'content' => $contentBlocks,
            'list' => $tableOfContents,
            'category' => $structure['category'],
            'tags' => $structure['tags'],
            'author_id' => $author->id,
            'cover_image' => $coverImage,
            'status' => 'draft',
            'featured' => false,
            'reading_time' => $this->estimateReadingTime($contentBlocks),
        ]);
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
                'themes' => ['jardinería', 'plantas'],
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
        $themesString = implode(', ', $authorAttributes['themes']);

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
- Temas principales: {$themesString}

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
            "description": "Descripción de qué debe contener este bloque"
        }
    ]
}

{$lengthInstruction} incluyendo:
- Al menos un heading
- Al menos 2-3 párrafos
- Al menos 1 imagen
- Opcionalmente: lista o quote
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
                'paragraph' => $this->generateParagraph($block['description'], $authorAttributes),
                'heading' => $this->generateHeading($block['description']),
                'image' => $this->generateImage($block['description']),
                'list' => $this->generateList($block['description'], $authorAttributes),
                'quote' => $this->generateQuote($block['description']),
                default => $this->generateParagraph($block['description'], $authorAttributes),
            };
        }

        return $contentBlocks;
    }

    private function generateParagraph(string $description, array $authorAttributes): array
    {
        $prompt = <<<PROMPT
Genera un párrafo para un blog de jardinería basado en esta descripción:
{$description}

IMPORTANTE - Escribe con estas características del autor:
- Tono: {$authorAttributes['tone']}
- Personalidad: {$authorAttributes['personality']}

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

    private function generateImage(string $description): array
    {
        // Generar un prompt detallado para la imagen
        $imagePrompt = $this->generateImagePrompt($description);

        // Generar la imagen
        $imageUrl = $this->imageGenerator->generate($imagePrompt, [
            'width' => 1200,
            'height' => 800,
        ]);

        return [
            'type' => 'image',
            'data' => [
                'url' => $imageUrl,
                'alt' => $description,
                'caption' => $description,
            ],
        ];
    }

    private function generateImagePrompt(string $description): string
    {
        $prompt = <<<PROMPT
Based on this gardening blog description: {$description}

Create a detailed, photorealistic image of plants/gardening. Include:
- A lush, tropical composition featuring exotic foliage with rich textures and natural imperfections.
- Warm cinematic color grading with slightly granular film texture, consistent color standardization across the entire image.
- Soft, diffused golden-hour lighting filtering through the leaves, creating gentle highlights and deep, warm shadows.
- A balanced, immersive composition centered on the main plant subject, surrounded by complementary greenery and subtle depth-of-field for a natural, atmospheric look.
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
     * Generate cover image for the post.
     */
    private function generateCoverImage(string $title, string $excerpt): string
    {
        $prompt = <<<PROMPT
Create a captivating hero image for a gardening blog post.
Theme inspiration: {$excerpt}
Requirements:
- Eye-catching, professional hero image (no text, no typography, no logos, no watermarks)
- High-quality, photorealistic style with lush plants and vibrant greenery
- Warm, inviting color palette with natural lighting
- Cinematic composition with depth and visual interest
- Works well as a banner/cover image
PROMPT;

        return $this->imageGenerator->generate($prompt, [
            'width' => 1200,
            'height' => 675,
        ]);
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
