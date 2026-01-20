<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\ImageGenerationRequest;

class PersonaFieldAssistantService
{
    public function __construct(
        private TextGeneratorInterface $textGenerator,
        private ImageGeneratorInterface $imageGenerator
    ) {}

    /**
     * Generate suggestions for a specific persona field.
     */
    public function generateFieldSuggestion(
        string $fieldType,
        string $userPrompt,
        array $existingContext = []
    ): array {
        // Si es avatar_url, usar el generador de imágenes Banana
        if ($fieldType === 'avatar_url') {
            return $this->generateAvatar($userPrompt, $existingContext);
        }

        $systemPrompt = $this->buildSystemPrompt($fieldType);
        $contextPrompt = $this->buildContextPrompt($existingContext);
        $fullPrompt = $this->buildUserPrompt($fieldType, $userPrompt, $contextPrompt);

        try {
            $response = $this->textGenerator->generate($fullPrompt, [
                'system' => $systemPrompt,
                'max_tokens' => $this->getMaxTokensForField($fieldType),
            ]);

            return $this->parseResponse($fieldType, $response);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate avatar image using Banana API.
     */
    private function generateAvatar(string $userPrompt, array $existingContext): array
    {
        try {
            $authorName = $existingContext['name'] ?? 'persona de jardinería';
            $authorId = $existingContext['author_id'] ?? null;

            // Crear un prompt descriptivo para generar un retrato realista
            $imagePrompt = 'Professional photorealistic portrait of a friendly gardening blog author. Profile photo style, neutral background, natural lighting, documentary photography style. Approachable and warm person.';

            \Log::info('Generando avatar con Banana', [
                'authorName' => $authorName,
                'authorId' => $authorId,
                'prompt' => $imagePrompt,
            ]);

            // Generar la imagen usando Banana con aspecto ratio cuadrado para avatar
            $taskId = $this->imageGenerator->generate($imagePrompt, [
                'aspectRatio' => '1:1',
                'resolution' => '2K',
            ]);

            \Log::info('Avatar taskId generado', ['taskId' => $taskId]);

            // Crear registro de ImageGenerationRequest si tenemos el author_id
            if ($authorId) {
                ImageGenerationRequest::create([
                    'external_id' => $taskId,
                    'targetable_type' => \App\Models\Author::class,
                    'targetable_id' => $authorId,
                    'prompt' => $imagePrompt,
                    'size' => '1:1',
                    'status' => 'pending',
                    'metadata' => [
                        'attribute' => 'avatar_url',
                        'author_name' => $authorName,
                    ],
                ]);

                \Log::info('ImageGenerationRequest creado para Author', [
                    'taskId' => $taskId,
                    'authorId' => $authorId,
                ]);
            }

            // Retornar mensaje indicando que se está procesando
            // La URL se actualizará automáticamente cuando el webhook de Banana se complete
            return [
                'success' => true,
                'type' => 'text',
                'value' => "🎨 Generando imagen... (TaskID: {$taskId})",
                'taskId' => $taskId,
                'pending' => true,
            ];
        } catch (\Exception $e) {
            \Log::error('Error generando avatar con Banana', [
                'error' => $e->getMessage(),
                'context' => $existingContext,
            ]);

            return [
                'success' => false,
                'error' => 'Error al generar la imagen: '.$e->getMessage(),
            ];
        }
    }

    private function buildSystemPrompt(string $fieldType): string
    {
        return match ($fieldType) {
            'name' => 'Genera un nombre realista y memorable para un autor ficticio de un blog de jardinería. El nombre debe coincidir con la descripción de la persona proporcionada. Devuelve SOLO el nombre, nada más. RESPONDE EN ESPAÑOL.',

            'background_story' => 'Estás ayudando a crear una persona de autor ficticia para un blog de jardinería. Genera una historia de fondo convincente y realista. Escribe en tercera persona. Mantén 2-3 párrafos. ESCRIBE TODO EN ESPAÑOL.',

            'personality_traits' => 'Genera una lista de 5-7 rasgos de personalidad para un autor ficticio. Devuelve SOLO un array JSON de strings EN ESPAÑOL. Cada rasgo debe ser específico y relevante para cómo escribirían.',

            'expertise_areas' => 'Genera una lista de 4-6 áreas de experiencia en jardinería/plantas. Devuelve SOLO un array JSON de strings EN ESPAÑOL. Sé específico (ej: "plantas tropicales de interior" no solo "plantas").',

            'catchphrases' => 'Genera 4-6 frases características o expresiones distintivas que este autor usaría naturalmente. Devuelve SOLO un array JSON EN ESPAÑOL. Hazlas memorables pero no clichés.',

            'quirks' => 'Genera 3-5 peculiaridades o hábitos de escritura. Devuelve SOLO un array JSON EN ESPAÑOL. Ejemplos: "siempre empieza con una pregunta", "usa metáforas de plantas", "hace referencias a las estaciones".',

            'recurring_topics' => 'Genera 4-6 temas que este autor menciona frecuentemente o entrelaza en su escritura. Devuelve SOLO un array JSON EN ESPAÑOL.',

            'avoided_elements' => 'Genera 3-5 cosas sobre las que este autor nunca escribiría o formas en las que nunca escribiría. Devuelve SOLO un array JSON EN ESPAÑOL.',

            'voice_description' => 'Describe cómo escribe este autor: sus patrones de oraciones, elección de palabras, ritmo. Escribe 2-3 oraciones EN ESPAÑOL.',

            default => 'Estás ayudando a crear una persona de autor ficticia para un blog de jardinería. RESPONDE EN ESPAÑOL.',
        };
    }

    private function buildContextPrompt(array $existingContext): string
    {
        if (empty($existingContext)) {
            return '';
        }

        $context = "Considera estos atributos existentes de la persona:\n";

        if (! empty($existingContext['name'])) {
            $context .= "Nombre: {$existingContext['name']}\n";
        }

        if (! empty($existingContext['background_story'])) {
            $context .= 'Historia: '.substr($existingContext['background_story'], 0, 100)."...\n";
        }

        if (! empty($existingContext['tone'])) {
            $context .= "Tono: {$existingContext['tone']}\n";
        }

        return $context."\n";
    }

    private function buildUserPrompt(string $fieldType, string $userPrompt, string $contextPrompt): string
    {
        $prompt = $contextPrompt;
        $prompt .= "Solicitud: {$userPrompt}\n\n";

        $isArrayField = in_array($fieldType, [
            'personality_traits', 'expertise_areas', 'catchphrases',
            'quirks', 'recurring_topics', 'avoided_elements',
        ]);

        if ($isArrayField) {
            $prompt .= "Devuelve SOLO un array JSON válido. Sin explicaciones, sin markdown, solo el array.\n";
            $prompt .= 'Formato de ejemplo: ["item1", "item2", "item3"]';
        }

        return $prompt;
    }

    private function getMaxTokensForField(string $fieldType): int
    {
        return match ($fieldType) {
            'name' => 50,
            'avatar_url' => 100,
            'background_story' => 500,
            'personality_traits', 'expertise_areas', 'catchphrases', 'quirks', 'recurring_topics', 'avoided_elements' => 300,
            default => 200,
        };
    }

    private function parseResponse(string $fieldType, string $response): array
    {
        $response = trim($response);

        // Check if this is an array field
        $isArrayField = in_array($fieldType, [
            'personality_traits', 'expertise_areas', 'catchphrases',
            'quirks', 'recurring_topics', 'avoided_elements',
        ]);

        if ($isArrayField) {
            // Try to extract JSON array from response
            // Sometimes AI wraps it in markdown code blocks
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
}
