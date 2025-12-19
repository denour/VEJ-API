<?php

namespace App\Services\AI;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Product;

class ProductGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {}

    /**
     * Generate a Product from a simple title using AI (text-only).
     */
    public function generate(string $title): Product
    {
        $prompt = $this->buildPrompt($title);

        $raw = $this->textGenerator->generate($prompt, [
            'system' => 'Eres un experto en e-commerce de plantas y jardinería. Debes responder únicamente con JSON válido y estricto.',
        ]);

        $data = $this->safeDecodeJson($raw);

        // Normalize and provide sane defaults
        $careLevels = ['easy', 'medium', 'hard'];
        $levels = ['low', 'medium', 'high'];
        $conditions = ['seedling', 'young', 'mature'];
        $sizes = ['small', 'medium', 'large', 'xl'];
        $types = ['sale', 'trade', 'free'];

        $type = in_array($data['type'] ?? null, $types, true) ? $data['type'] : 'sale';
        $price = $type === 'sale' ? (isset($data['price']) ? (float) $data['price'] : 199.0) : null;

        // Try to generate a product image based on the attributes
        $image = $this->generateImage($title, $data);

        $payload = [
            'image' => $image,
            'images' => $image ? [$image] : [],
            'name' => $data['name'] ?? $title,
            'scientific_name' => $data['scientific_name'] ?? null,
            'species_id' => null,
            'care_level' => in_array($data['care_level'] ?? null, $careLevels, true) ? $data['care_level'] : 'easy',
            'sunlight' => in_array($data['sunlight'] ?? null, $levels, true) ? $data['sunlight'] : 'medium',
            'watering' => in_array($data['watering'] ?? null, $levels, true) ? $data['watering'] : 'medium',
            'condition' => in_array($data['condition'] ?? null, $conditions, true) ? $data['condition'] : 'young',
            'size' => in_array($data['size'] ?? null, $sizes, true) ? $data['size'] : 'medium',
            'is_rare' => (bool) ($data['is_rare'] ?? false),
            'type' => $type,
            'price' => $price,
            'currency' => strtoupper($data['currency'] ?? 'MXN'),
            'rating' => min(5.0, max(0.0, (float) ($data['rating'] ?? 0))),
            'reviews' => (int) ($data['reviews'] ?? 0),
            'in_stock' => (bool) ($data['in_stock'] ?? true),
            'quantity' => isset($data['quantity']) ? (int) $data['quantity'] : null,
        ];

        return Product::create($payload);
    }

    private function buildPrompt(string $title): string
    {
        return <<<PROMPT
Genera los detalles de un producto de plantas para una tienda llamada "Vida en el Jardín".
El título del producto es: "{$title}".

Responde EXCLUSIVAMENTE con JSON válido siguiendo esta forma y valores permitidos:
{
  "name": string, // nombre del producto, puede ser igual al título
  "scientific_name": string|null,
  "care_level": "easy"|"medium"|"hard",
  "sunlight": "low"|"medium"|"high",
  "watering": "low"|"medium"|"high",
  "condition": "seedling"|"young"|"mature",
  "size": "small"|"medium"|"large"|"xl",
  "is_rare": boolean,
  "type": "sale"|"trade"|"free",
  "price": number|null, // null si type != "sale"
  "currency": "MXN",
  "rating": number, // 0..5 con 1 decimal aprox
  "reviews": integer,
  "in_stock": boolean,
  "quantity": integer|null
}

No incluyas comentarios ni texto adicional, solo el JSON.
PROMPT;
    }

    private function generateImage(string $title, array $data): ?string
    {
        $name = $data['name'] ?? $title;
        $scientific = $data['scientific_name'] ?? '';
        $condition = $data['condition'] ?? 'young';
        $size = $data['size'] ?? 'medium';
        $isRare = ! empty($data['is_rare']);

        $rareText = $isRare ? 'rare cultivar' : '';

        $prompt = <<<PROMPT
Photorealistic product photo of a potted plant for an online store.
Plant: {$name} {$scientific}
Details: {$condition} condition, {$size} size, {$rareText}
Style: clean studio lighting, soft shadows, neutral background, high detail, sharp focus, lifestyle-ready
No text, no watermark, centered composition
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

    /**
     * Tolerant JSON decode. Tries to extract a JSON block if wrapped in markdown.
     */
    private function safeDecodeJson(string $raw): array
    {
        $candidate = trim($raw);

        // Extract code block if present
        if (preg_match('/```(?:json)?\n(.+?)\n```/is', $candidate, $m)) {
            $candidate = $m[1];
        }

        $data = json_decode($candidate, true);
        if (is_array($data)) {
            return $data;
        }

        // Fallback with minimal defaults
        return [
            'name' => $candidate ?: 'Producto',
        ];
    }
}
