<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\ImageGenerationRequest;
use App\Models\Species;

class SpeciesGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {}

    /**
     * Generate a Species from a simple title using AI (text-only).
     */
    public function generate(string $title): Species
    {
        $prompt = $this->buildPrompt($title);

        $raw = $this->textGenerator->generate($prompt, [
            'system' => 'Eres un botánico experto. Responde únicamente con JSON válido y estricto.',
        ]);

        $data = $this->safeDecodeJson($raw);

        $careLevels = ['easy', 'medium', 'hard'];
        $levels = ['low', 'medium', 'high'];
        $toxicities = ['none', 'pets', 'humans', 'both'];
        $growthRates = ['slow', 'medium', 'fast'];

        // Create species first
        $species = Species::create([
            'common_name' => $data['common_name'] ?? $title,
            'scientific_name' => $data['scientific_name'] ?? null,
            'family' => $data['family'] ?? null,
            'origin' => $data['origin'] ?? null,
            'description' => $data['description'] ?? null,
            'care_level' => in_array($data['care_level'] ?? null, $careLevels, true) ? $data['care_level'] : 'easy',
            'sunlight' => in_array($data['sunlight'] ?? null, $levels, true) ? $data['sunlight'] : 'medium',
            'watering' => in_array($data['watering'] ?? null, $levels, true) ? $data['watering'] : 'medium',
            'toxicity' => in_array($data['toxicity'] ?? null, $toxicities, true) ? $data['toxicity'] : 'none',
            'growth_rate' => in_array($data['growth_rate'] ?? null, $growthRates, true) ? $data['growth_rate'] : 'medium',
            'max_height_cm' => isset($data['max_height_cm']) ? (int) $data['max_height_cm'] : null,
        ]);

        // Generate a species image after creating the species
        $this->generateImage($species, $data);

        return $species;
    }

    private function buildPrompt(string $title): string
    {
        return <<<PROMPT
Genera la ficha de una especie vegetal relacionada con "{$title}" para un catálogo de jardinería.

Responde SOLO con JSON válido con esta forma exacta:
{
  "common_name": string, // puede ser igual al título
  "scientific_name": string|null,
  "family": string|null,
  "origin": string|null,
  "description": string|null,
  "care_level": "easy"|"medium"|"hard",
  "sunlight": "low"|"medium"|"high",
  "watering": "low"|"medium"|"high",
  "toxicity": "none"|"pets"|"humans"|"both",
  "growth_rate": "slow"|"medium"|"fast",
  "max_height_cm": integer|null
}

No incluyas texto adicional, solo el JSON.
PROMPT;
    }

    private function generateImage(Species $species, array $data): void
    {
        $common = $data['common_name'] ?? $species->common_name;
        $scientific = $data['scientific_name'] ?? '';

        $prompt = <<<PROMPT
Photorealistic botanical portrait of a plant species.
Species: {$common} {$scientific}
Lighting: natural soft daylight
Details: clear leaves texture, stems and overall morphology, accurate color, shallow depth of field
Background: neutral or softly blurred natural background
Style: high-quality macro/close-up botanical photography, no text, no watermark
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
                'targetable_type' => Species::class,
                'targetable_id' => $species->id,
                'prompt' => $prompt,
                'status' => 'pending',
                'metadata' => [
                    'attribute' => 'image',
                    'model_name' => 'Species',
                ],
            ]);
        } catch (\Throwable $e) {
            // Log error but don't fail species creation
            \Illuminate\Support\Facades\Log::error('Failed to generate species image', [
                'species_id' => $species->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function safeDecodeJson(string $raw): array
    {
        $candidate = trim($raw);
        if (preg_match('/```(?:json)?\n(.+?)\n```/is', $candidate, $m)) {
            $candidate = $m[1];
        }

        $data = json_decode($candidate, true);
        if (is_array($data)) {
            return $data;
        }

        return [
            'common_name' => $candidate ?: 'Especie',
        ];
    }
}
