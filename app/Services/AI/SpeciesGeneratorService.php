<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
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

        // Generate a species image
        $image = $this->generateImage($title, $data);

        $payload = [
            'common_name' => $data['common_name'] ?? $title,
            'scientific_name' => $data['scientific_name'] ?? null,
            'family' => $data['family'] ?? null,
            'origin' => $data['origin'] ?? null,
            'description' => $data['description'] ?? null,
            'care_level' => in_array($data['care_level'] ?? null, $careLevels, true) ? $data['care_level'] : 'easy',
            'sunlight' => in_array($data['sunlight'] ?? null, $levels, true) ? $data['sunlight'] : 'medium',
            'watering' => in_array($data['watering'] ?? null, $levels, true) ? $data['watering'] : 'medium',
            'image' => $image,
            'images' => $image ? [$image] : [],
            'toxicity' => in_array($data['toxicity'] ?? null, $toxicities, true) ? $data['toxicity'] : 'none',
            'growth_rate' => in_array($data['growth_rate'] ?? null, $growthRates, true) ? $data['growth_rate'] : 'medium',
            'max_height_cm' => isset($data['max_height_cm']) ? (int) $data['max_height_cm'] : null,
        ];

        return Species::create($payload);
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

    private function generateImage(string $title, array $data): ?string
    {
        $common = $data['common_name'] ?? $title;
        $scientific = $data['scientific_name'] ?? '';
        $sunlight = $data['sunlight'] ?? 'medium';
        $watering = $data['watering'] ?? 'medium';

        $prompt = <<<PROMPT
Photorealistic botanical portrait of a plant species.
Species: {$common} {$scientific}
Lighting: natural soft daylight
Details: clear leaves texture, stems and overall morphology, accurate color, shallow depth of field
Background: neutral or softly blurred natural background
Style: high-quality macro/close-up botanical photography, no text, no watermark
PROMPT;

        try {
            return $this->imageGenerator->generate($prompt, [
                'width' => 1024,
                'height' => 1024,
            ]);
        } catch (\Throwable $e) {
            return null;
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
