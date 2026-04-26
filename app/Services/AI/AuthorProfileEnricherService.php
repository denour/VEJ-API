<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthorProfileEnricherService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly VoiceBibleGeneratorService $voiceBibleGenerator,
    ) {}

    /**
     * Enrich an existing author with a full persona profile.
     * Uses their name and existing posts to create a differentiated voice.
     */
    public function enrichAuthor(Author $author, array $existingProfiles = []): Author
    {
        $existingPosts = $author->posts()->latest()->take(20)->pluck('title')->toArray();

        $profile = $this->generateProfile($author->name, $existingPosts, $existingProfiles);

        $author->update([
            'background_story' => $profile['background_story'],
            'personality_traits' => $profile['personality_traits'],
            'expertise_areas' => $profile['expertise_areas'],
            'sentence_style' => $profile['sentence_style'],
            'vocabulary_level' => $profile['vocabulary_level'],
            'tone' => $profile['tone'],
            'formality' => $profile['formality'],
            'catchphrases' => $profile['catchphrases'],
            'quirks' => $profile['quirks'],
            'recurring_topics' => $profile['recurring_topics'],
            'avoided_elements' => $profile['avoided_elements'],
        ]);

        // Generate voice bible using the now-enriched author
        $author->refresh();
        $voiceBible = $this->voiceBibleGenerator->generate($author);
        $author->update(['voice_bible' => $voiceBible]);

        Log::info("Author profile enriched: {$author->name}");

        return $author->refresh();
    }

    /**
     * Create a new author with a fully differentiated profile.
     */
    public function createNewAuthor(array $existingProfiles = []): Author
    {
        $nameData = $this->generateNewAuthorName($existingProfiles);

        $author = Author::create([
            'name' => $nameData['name'],
            'slug' => Str::slug($nameData['name']),
            'is_active' => true,
        ]);

        return $this->enrichAuthor($author, $existingProfiles);
    }

    /**
     * Generate a complete persona profile via AI.
     */
    private function generateProfile(string $authorName, array $existingPosts, array $existingProfiles): array
    {
        $existingProfilesSummary = $this->summarizeExistingProfiles($existingProfiles);
        $existingPostsList = ! empty($existingPosts) ? implode("\n- ", $existingPosts) : 'Ninguno aún';

        $prompt = <<<PROMPT
Genera un perfil completo de personalidad para un autor de blog de jardinería llamado "{$authorName}".

CONTEXTO DEL BLOG: "Vida en el Jardín" — un blog mexicano sobre plantas, jardinería urbana, y vida verde. Los lectores son aficionados hispanohablantes.

POSTS YA ESCRITOS por este autor:
- {$existingPostsList}

PERFILES DE OTROS AUTORES DEL MISMO BLOG (el nuevo perfil DEBE ser diferente y complementario):
{$existingProfilesSummary}

GENERA un perfil JSON con EXACTAMENTE estos campos. El perfil debe ser ÚNICO, diferenciado de los otros autores, y sentirse como una persona REAL con opiniones y estilo propios:

{
  "background_story": "Historia personal de 3-4 oraciones. De dónde es, cómo descubrió las plantas, qué experiencia tiene. Debe sonar auténtico y mexicano.",
  "personality_traits": ["rasgo1", "rasgo2", "rasgo3", "rasgo4", "rasgo5"],
  "expertise_areas": ["especialidad1", "especialidad2", "especialidad3", "especialidad4"],
  "sentence_style": "Descripción del estilo de oraciones (ej: 'Mezcla oraciones cortas punzantes con explicaciones largas y detalladas')",
  "vocabulary_level": "Nivel de vocabulario (ej: 'técnico pero accesible', 'coloquial mexicano', 'poético-naturalista')",
  "tone": "Tono general (ej: 'maternal y protector', 'científico pero cálido', 'irreverente y divertido')",
  "formality": "Nivel de formalidad (ej: 'informal con tuteo', 'semi-formal educativo', 'casual como plática entre amigos')",
  "catchphrases": ["frase1", "frase2", "frase3"],
  "quirks": ["manía de escritura 1", "manía 2"],
  "recurring_topics": ["tema recurrente 1", "tema 2", "tema 3"],
  "avoided_elements": ["cosa que nunca hace 1", "cosa 2", "cosa 3"]
}

REGLAS:
- El perfil debe ser en ESPAÑOL
- Debe reflejar diversidad de México (no todos del DF)
- Las especialidades NO deben repetir las de otros autores
- El tono debe ser claramente diferente al de los demás
- Las catchphrases deben sonar naturales, no forzadas
- Responde SOLO con el JSON, sin explicaciones
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'temperature' => 0.9,
            'max_tokens' => 800,
        ]);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $profile = json_decode(trim($response), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse author profile JSON', ['response' => $response]);
            throw new \RuntimeException('Failed to parse author profile: '.json_last_error_msg());
        }

        return $profile;
    }

    /**
     * Generate a name for a new author that fits the blog's identity.
     */
    private function generateNewAuthorName(array $existingProfiles): array
    {
        $existingNames = array_column($existingProfiles, 'name');
        $namesList = implode(', ', $existingNames);

        $prompt = <<<PROMPT
Genera un nombre completo para un nuevo autor de un blog mexicano de jardinería llamado "Vida en el Jardín".

Autores existentes: {$namesList}

El nuevo nombre debe:
- Ser un nombre mexicano realista (nombre + apellido)
- Sonar diferente a los existentes
- Reflejar diversidad regional de México

Responde SOLO con un JSON: {"name": "Nombre Completo"}
PROMPT;

        $response = $this->textGenerator->generate($prompt, [
            'temperature' => 0.9,
            'max_tokens' => 50,
        ]);

        $response = preg_replace('/^```json\s*/m', '', $response);
        $response = preg_replace('/\s*```$/m', '', $response);

        $data = json_decode(trim($response), true);

        if (! $data || empty($data['name'])) {
            return ['name' => 'Autor Nuevo '.random_int(1, 99)];
        }

        return $data;
    }

    /**
     * Summarize existing profiles to help AI differentiate the new one.
     */
    private function summarizeExistingProfiles(array $profiles): string
    {
        if (empty($profiles)) {
            return 'Ninguno definido aún.';
        }

        $summary = '';
        foreach ($profiles as $profile) {
            $name = $profile['name'] ?? 'Desconocido';
            $tone = $profile['tone'] ?? 'no definido';
            $expertise = is_array($profile['expertise_areas'] ?? null) ? implode(', ', $profile['expertise_areas']) : 'no definido';
            $style = $profile['sentence_style'] ?? 'no definido';
            $summary .= "- {$name}: tono={$tone}, expertise={$expertise}, estilo={$style}\n";
        }

        return $summary;
    }
}
