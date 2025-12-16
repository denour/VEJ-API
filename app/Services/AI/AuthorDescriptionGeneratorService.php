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
    public function extractAttributes(string $detailedDescription): array
    {
        // Parse the structured format
        $lines = explode("\n", $detailedDescription);
        $attributes = [
            'tone' => '',
            'personality' => '',
            'themes' => [],
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'Tono:')) {
                $attributes['tone'] = trim(str_replace('Tono:', '', $line));
            } elseif (str_starts_with($line, 'Personalidad:')) {
                $attributes['personality'] = trim(str_replace('Personalidad:', '', $line));
            } elseif (str_starts_with($line, 'Temas:')) {
                $themesString = trim(str_replace('Temas:', '', $line));
                $attributes['themes'] = array_map('trim', explode(',', $themesString));
            }
        }

        return $attributes;
    }
}
