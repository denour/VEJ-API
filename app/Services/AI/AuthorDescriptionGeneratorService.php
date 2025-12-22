<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;

class AuthorDescriptionGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
    ) {}

    /**
     * Generate a detailed description for an author based on their basic info.
     */
    public function generateDetailedDescription(Author $author): string
    {
        $prompt = <<<PROMPT
Genera una descripción detallada para un autor de blog de jardinería basándote en esta información:

Nombre: {$author->name}
Descripción básica: {$author->description}

La descripción detallada debe incluir:
1. **Tono**: El estilo de comunicación (ejemplo: conversacional, técnico, entusiasta, profesional, cercano)
2. **Personalidad**: Los rasgos característicos del autor (ejemplo: apasionado, educativo, amigable, experto, accesible)
3. **Temas principales**: Las áreas de especialización o temas que más aborda (ejemplo: plantas tropicales, cuidados básicos, jardinería urbana, técnicas avanzadas)

Responde en formato de texto estructurado:
Tono: [descripción del tono]
Personalidad: [descripción de la personalidad]
Temas: [lista de temas principales separados por comas]

IMPORTANTE: Responde ÚNICAMENTE con el formato especificado, sin introducción ni conclusión.
PROMPT;

        return $this->textGenerator->generate($prompt, [
            'temperature' => 0.7,
            'max_tokens' => 300,
        ]);
    }

    /**
     * Extract attributes from a detailed description.
     */
    public function extractAttributes(string|array $detailedDescription): array
    {
        // Si ya es un array (desde el modelo con cast), usarlo directamente
        if (is_array($detailedDescription)) {
            return [
                'tone' => $detailedDescription['tone'] ?? 'conversacional y educativo',
                'personality' => $detailedDescription['personality'] ?? 'entusiasta',
                'writing_style' => $detailedDescription['writing_style'] ?? 'claro y accesible',
                'themes' => $detailedDescription['themes'] ?? ['jardinería', 'plantas'],
                'editorial_focus' => $detailedDescription['editorial_focus'] ?? 'educación práctica',
            ];
        }

        // Intentar decodificar como JSON primero (nuevo formato)
        $data = json_decode($detailedDescription, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return [
                'tone' => $data['tone'] ?? 'conversacional y educativo',
                'personality' => $data['personality'] ?? 'entusiasta',
                'writing_style' => $data['writing_style'] ?? 'claro y accesible',
                'themes' => $data['themes'] ?? ['jardinería', 'plantas'],
                'editorial_focus' => $data['editorial_focus'] ?? 'educación práctica',
            ];
        }

        // Fallback: Procesar el formato de texto estructurado antiguo
        $lines = explode("\n", $detailedDescription);
        $attributes = [
            'tone' => 'conversacional y educativo',
            'personality' => 'entusiasta',
            'writing_style' => 'claro y accesible',
            'themes' => ['jardinería', 'plantas'],
            'editorial_focus' => 'educación práctica',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'Tono:')) {
                $attributes['tone'] = trim(str_replace('Tono:', '', $line)) ?: $attributes['tone'];
            } elseif (str_starts_with($line, 'Personalidad:')) {
                $attributes['personality'] = trim(str_replace('Personalidad:', '', $line)) ?: $attributes['personality'];
            } elseif (str_starts_with($line, 'Temas:')) {
                $themesString = trim(str_replace('Temas:', '', $line));
                if (! empty($themesString)) {
                    $attributes['themes'] = array_map('trim', explode(',', $themesString));
                }
            }
        }

        return $attributes;
    }
}
