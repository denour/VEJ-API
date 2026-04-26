<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use App\Models\AuthorTopic;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TopicGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
    ) {}

    /**
     * Generate topic ideas for an author based on their expertise and existing content.
     *
     * @return Collection<int, AuthorTopic>
     */
    public function generateTopics(Author $author, int $count = 10): Collection
    {
        $existingPosts = $author->posts()
            ->latest()
            ->take(20)
            ->pluck('title')
            ->toArray();

        $existingTopics = $author->topics()
            ->pluck('topic')
            ->toArray();

        $expertiseList = implode(', ', $author->expertise_areas ?? ['jardinería general']);
        $recurringList = implode(', ', $author->recurring_topics ?? []);
        $tone = $author->tone ?? 'warm';

        $existingPostsList = ! empty($existingPosts) ? implode("\n- ", $existingPosts) : 'Ninguno aún';
        $existingTopicsList = ! empty($existingTopics) ? implode("\n- ", $existingTopics) : 'Ninguno';

        $prompt = <<<PROMPT
Genera {$count} ideas de temas para artículos de blog de jardinería.

AUTOR: {$author->name}
TONO: {$tone}
ESPECIALIDADES: {$expertiseList}
TEMAS RECURRENTES: {$recurringList}

POSTS YA PUBLICADOS (NO repetir estos temas):
- {$existingPostsList}

TEMAS YA EN PIPELINE (NO repetir estos):
- {$existingTopicsList}

CATEGORÍAS DISPONIBLES (usa variedad):
- Cuidado (mantenimiento, riego, poda, fertilización)
- Identificación (reconocer especies, familias, variedades)
- Decoración (arreglos, diseño con plantas, paisajismo)
- Herramientas (equipo, macetas, sustratos, accesorios)
- Consejos (tips prácticos, trucos, solución de problemas)

REGLAS:
- Los temas deben dar CONTINUIDAD a lo ya publicado (ej: si ya escribió sobre Pothos, un tema siguiente podría ser "Propagación avanzada de Pothos en agua")
- Cada tema debe ser específico y accionable, no genérico
- Varía las categorías — no pongas todo en la misma
- Los temas deben alinearse con las especialidades del autor
- Títulos en español, atractivos, entre 40-70 caracteres

Responde SOLO con un JSON array:
[
  {"topic": "Título del artículo", "category": "Categoría"},
  ...
]
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'temperature' => 0.8,
            'max_tokens' => 800,
        ]);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $topics = json_decode(trim($response), true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($topics)) {
            Log::error('Failed to parse topic generation response', [
                'author' => $author->name,
                'response' => $response,
            ]);

            return collect();
        }

        $created = collect();

        foreach ($topics as $topicData) {
            if (empty($topicData['topic'])) {
                continue;
            }

            $created->push(AuthorTopic::create([
                'author_id' => $author->id,
                'topic' => $topicData['topic'],
                'category' => $topicData['category'] ?? null,
            ]));
        }

        Log::info("Generated {$created->count()} topics for author {$author->name}");

        return $created;
    }
}
