<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\ImageGenerationRequest;

class PostContentAssistantService
{
    public function __construct(
        private TextGeneratorInterface $textGenerator,
        private ImageGeneratorInterface $imageGenerator
    ) {}

    /**
     * Generate content for a specific post field.
     */
    public function generateFieldContent(
        string $fieldType,
        string $userPrompt,
        array $context = []
    ): array {
        // Handle image generation separately
        if (in_array($fieldType, ['cover_image', 'block_image'])) {
            return $this->generateImage($userPrompt, $context, $fieldType);
        }

        $systemPrompt = $this->buildSystemPrompt($fieldType);
        $contextPrompt = $this->buildContextPrompt($context);
        $fullPrompt = $contextPrompt.$userPrompt;

        try {
            $response = $this->textGenerator->generate($fullPrompt, [
                'system' => $systemPrompt,
                'max_tokens' => $this->getMaxTokensForField($fieldType),
            ]);

            return $this->parseResponse($fieldType, $response);
        } catch (\Exception $e) {
            \Log::error('Post AI Assist Error', [
                'field' => $fieldType,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function buildSystemPrompt(string $fieldType): string
    {
        return match ($fieldType) {
            'title' => 'Eres un experto en escribir títulos atractivos para blogs de jardinería. Genera un título conciso, interesante y optimizado para SEO. El título debe ser entre 40-60 caracteres. Devuelve SOLO el título, sin comillas ni explicaciones. RESPONDE EN ESPAÑOL.',

            'excerpt' => 'Eres un experto en escribir resúmenes atractivos para blogs de jardinería. Genera un extracto de 2-3 oraciones que capture la esencia del contenido y enganche al lector. Devuelve SOLO el extracto. ESCRIBE EN ESPAÑOL.',

            'paragraph' => 'Eres un escritor experto de contenido de jardinería. Genera un párrafo informativo, bien escrito y útil. Usa un tono amigable y accesible. Devuelve SOLO el párrafo. ESCRIBE EN ESPAÑOL.',

            'heading' => 'Eres un experto en estructurar contenido. Genera un encabezado claro y descriptivo que organice el contenido. Devuelve SOLO el texto del encabezado, sin el nivel (H2, H3, etc). RESPONDE EN ESPAÑOL.',

            'list_items' => 'Eres un experto en contenido de jardinería. Genera una lista de 4-7 items informativos y útiles. Devuelve SOLO un array JSON de strings. RESPONDE EN ESPAÑOL.',

            'quote_content' => 'Eres un experto en seleccionar citas relevantes sobre jardinería. Genera una cita inspiradora o informativa relacionada con el tema. Devuelve SOLO la cita, sin comillas externas. RESPONDE EN ESPAÑOL.',

            'tags' => 'Eres un experto en SEO y categorización de contenido de jardinería. Genera 5-8 tags relevantes y específicos. Devuelve SOLO un array JSON de strings. RESPONDE EN ESPAÑOL.',

            default => 'Eres un asistente de escritura para blogs de jardinería. RESPONDE EN ESPAÑOL.',
        };
    }

    private function buildContextPrompt(array $context): string
    {
        if (empty($context)) {
            return '';
        }

        $prompt = "Contexto del post:\n";

        if (! empty($context['title'])) {
            $prompt .= "Título del post: {$context['title']}\n";
        }

        if (! empty($context['category'])) {
            $prompt .= "Categoría: {$context['category']}\n";
        }

        if (! empty($context['excerpt'])) {
            $prompt .= 'Extracto: '.substr($context['excerpt'], 0, 150)."...\n";
        }

        if (! empty($context['author_name'])) {
            $prompt .= "Autor: {$context['author_name']}\n";
        }

        return $prompt."\n";
    }

    private function getMaxTokensForField(string $fieldType): int
    {
        return match ($fieldType) {
            'title' => 100,
            'excerpt' => 200,
            'paragraph' => 500,
            'heading' => 100,
            'list_items' => 300,
            'quote_content' => 200,
            'tags' => 150,
            default => 300,
        };
    }

    private function parseResponse(string $fieldType, string $response): array
    {
        $response = trim($response);

        // Check if this is an array field (JSON response expected)
        $isArrayField = in_array($fieldType, ['list_items', 'tags']);

        if ($isArrayField) {
            // Try to extract JSON array from response
            if (preg_match('/```(?:json)?\s*(\[.*?\])\s*```/s', $response, $matches)) {
                $response = $matches[1];
            }

            $decoded = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return [
                    'success' => true,
                    'type' => 'array',
                    'value' => $decoded,
                ];
            }

            // Fallback: try to split by lines/commas
            $items = preg_split('/[,\n]+/', $response);
            $items = array_map('trim', $items);
            $items = array_filter($items);

            return [
                'success' => true,
                'type' => 'array',
                'value' => array_values($items),
            ];
        }

        return [
            'success' => true,
            'type' => 'text',
            'value' => $response,
        ];
    }

    private function generateImage(string $userPrompt, array $context, string $fieldType): array
    {
        try {
            $postTitle = $context['title'] ?? 'artículo de jardinería';
            $postCategory = $context['category'] ?? 'jardinería';
            $postId = $context['post_id'] ?? null;
            $blockId = $context['block_id'] ?? null;

            // Build a descriptive image prompt based on context
            $imagePrompt = match ($fieldType) {
                'cover_image' => "Professional high-quality featured image for a gardening blog post titled '{$postTitle}'. Category: {$postCategory}. Photorealistic, vibrant colors, natural lighting, inspiring gardening scene.",
                'block_image' => "Illustrative image for gardening blog content. Context: {$userPrompt}. Photorealistic, educational, clear and detailed.",
                default => $userPrompt,
            };

            \Log::info('Generando imagen para post con Banana', [
                'postTitle' => $postTitle,
                'postId' => $postId,
                'blockId' => $blockId,
                'fieldType' => $fieldType,
                'prompt' => $imagePrompt,
            ]);

            $taskId = $this->imageGenerator->generate($imagePrompt, [
                'aspectRatio' => $fieldType === 'cover_image' ? '16:9' : '4:3',
                'resolution' => '2K',
            ]);

            \Log::info('Imagen taskId generado', ['taskId' => $taskId]);

            // For block images with a block_id, target the PostBlock directly
            if ($fieldType === 'block_image' && $blockId) {
                ImageGenerationRequest::create([
                    'external_id' => $taskId,
                    'targetable_type' => \App\Models\PostBlock::class,
                    'targetable_id' => $blockId,
                    'prompt' => $imagePrompt,
                    'size' => '4:3',
                    'status' => 'pending',
                    'metadata' => [
                        'attribute' => 'data.url',
                        'post_title' => $postTitle,
                    ],
                ]);
            } elseif ($postId) {
                // For cover images or blocks without ID, target the Post
                ImageGenerationRequest::create([
                    'external_id' => $taskId,
                    'targetable_type' => \App\Models\Post::class,
                    'targetable_id' => $postId,
                    'prompt' => $imagePrompt,
                    'size' => $fieldType === 'cover_image' ? '16:9' : '4:3',
                    'status' => 'pending',
                    'metadata' => [
                        'attribute' => $fieldType === 'cover_image' ? 'cover_image' : 'block_image',
                        'post_title' => $postTitle,
                    ],
                ]);
            }

            return [
                'success' => true,
                'type' => 'text',
                'value' => "🎨 Generando imagen... (TaskID: {$taskId})",
                'taskId' => $taskId,
                'pending' => true,
            ];
        } catch (\Exception $e) {
            \Log::error('Error generando imagen para post con Banana', [
                'error' => $e->getMessage(),
                'context' => $context,
            ]);

            return [
                'success' => false,
                'error' => 'Error al generar la imagen: '.$e->getMessage(),
            ];
        }
    }
}
