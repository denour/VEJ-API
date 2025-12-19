<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Faq;

class FaqGeneratorService
{
    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
    ) {}

    public function generate(string $topicOrQuestion): Faq
    {
        $prompt = $this->buildPrompt($topicOrQuestion);

        $raw = $this->textGenerator->generate($prompt, [
            'system' => 'You are a gardening help center writer. Respond with strict valid JSON only.',
        ]);

        $data = $this->safeDecodeJson($raw);

        $payload = [
            'category' => $data['category'] ?? 'General',
            'question' => $data['question'] ?? $topicOrQuestion,
            'answer' => $data['answer'] ?? 'Information forthcoming.',
        ];

        return Faq::create($payload);
    }

    private function buildPrompt(string $topicOrQuestion): string
    {
        return <<<PROMPT
Create a helpful gardening FAQ entry based on the following topic or question:
"{$topicOrQuestion}"

Respond ONLY with JSON in the following schema:
{
  "category": "One of: Care, Identification, Tools, Tips, Troubleshooting, General",
  "question": "Clear, concise question in Spanish",
  "answer": "Helpful, accurate answer in Spanish (2-4 short paragraphs). Use simple formatting like line breaks."
}
PROMPT;
    }

    private function safeDecodeJson(string $raw): array
    {
        $candidate = trim($raw);
        if (preg_match('/```(?:json)?\n(.+?)\n```/is', $candidate, $m)) {
            $candidate = $m[1];
        }
        $data = json_decode($candidate, true);

        return is_array($data) ? $data : [];
    }
}
