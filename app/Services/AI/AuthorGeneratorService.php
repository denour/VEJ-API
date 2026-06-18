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
            'detailed_description' => isset($content['detailed_description'])
                ? json_encode($content['detailed_description'], JSON_UNESCAPED_UNICODE)
                : null,
        ]);

        // Generate avatar after creating the author
        $this->generateAvatar($author);

        return $author;
    }

    private function generateDescriptions(string $name): array
    {
        $prompt = <<<PROMPT
Eres el editor jefe y curador de contenido del blog de jardinería "Vida en el Jardín",
un proyecto editorial en español enfocado en botánica, jardinería consciente,
cultivo doméstico, plantas nativas y educación ambiental.

Tu tarea es generar la biografía editorial de un autor del blog con el nombre: {$name}.

El autor debe sentirse real, humano y coherente con el espíritu del proyecto:
- Cercano, didáctico y apasionado por las plantas.
- Con experiencia práctica en jardinería, no solo teórica.
- Con interés por la sostenibilidad, el respeto a la naturaleza y el aprendizaje continuo.
- Escritura clara, accesible y honesta, evitando tecnicismos innecesarios.

Los temas (`themes`) deben ser múltiples y variados, siempre relacionados con
jardinería y plantas, pero pueden incluir subtemas como cultivo, propagación,
cuidado, diseño de jardines, plantas nativas, huertos, ecología, botánica básica,
plantas medicinales, experiencias personales u observación de la naturaleza.

Responde EXCLUSIVAMENTE con JSON válido y bien formado, sin texto adicional,
usando exactamente esta estructura:

{
  "description": "Resumen breve del autor en 1–2 frases, en español, tono cercano y natural.",
  "detailed_description": {
    "role": "Rol del autor dentro de Vida en el Jardín",
    "bio_long": "Biografía extensa en párrafo completo, con narrativa humana y pasión por las plantas.",
    "areas_of_expertise": [
      "Lista de áreas de especialización relacionadas con jardinería y botánica"
    ],
    "writing_style": "Descripción del estilo de escritura del autor",
    "editorial_focus": [
      "Temas principales que aborda en sus artículos"
    ],
    "themes": [
      "Lista de múltiples temas relacionados con jardinería y plantas"
    ],
    "tone": "conversacional y educativo",
    "personality": "entusiasta",
    "values": [
      "Valores personales y editoriales relacionados con la naturaleza y el cuidado del entorno"
    ],
    "experience_level": "Nivel de experiencia del autor",
    "target_audience": "Tipo de lectores a los que se dirige",
    "location_context": "Contexto geográfico o climático desde el cual escribe (si aplica)",
    "signature_phrase": "Frase o enfoque característico del autor",
    "seo_keywords": [
      "Palabras clave asociadas al autor y su contenido"
    ]
  }
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
Ultra-realistic professional photographic studio headshot of a real human gardening expert named {$author->name} (use the name ONLY to infer a fitting, natural appearance — never write the name or any text in the image).
Shot on a DSLR with an 85mm lens, true-to-life skin texture and fine detail, soft diffused lighting, subtle smile, shallow depth of field, neutral softly blurred background.
It must look like an authentic photograph of a real person — NOT a 3D render, NOT CGI, NOT an illustration, NOT a painting, NOT stylized.
Absolutely no text, no words, no letters, no name captions, no titles, no logos, no watermarks, no borders.
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
