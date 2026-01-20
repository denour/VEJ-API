<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;

class VoiceBibleGeneratorService
{
    private const TARGET_WORDS = 300;

    public function __construct(
        private TextGeneratorInterface $textGenerator
    ) {}

    public function generate(Author $persona): string
    {
        $prompt = $this->buildPrompt($persona);

        $response = $this->textGenerator->generate($prompt, [
            'system' => $this->getSystemPrompt(),
            'max_tokens' => 600, // ~300 words
        ]);

        return $this->formatVoiceBible($response, $persona);
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
You are creating a Voice Bible - a concise style guide that captures exactly how a specific author writes.
This guide will be used by AI to generate content in this author's voice.

The Voice Bible must be EXACTLY 300 words (no more, no less).

Structure it as:
1. ESSENCE (2-3 sentences): Who is this writer at their core?
2. VOICE SIGNATURE (3-4 sentences): Sentence patterns, vocabulary, rhythm
3. DO's (bullet list): 5 specific things to always do
4. DON'Ts (bullet list): 5 specific things to never do
5. SAMPLE PHRASES: 3 example sentences in their voice

Be specific and actionable. Avoid generic advice.
PROMPT;
    }

    private function buildPrompt(Author $persona): string
    {
        $prompt = "Create a 300-word Voice Bible for this author:\n\n";
        $prompt .= "**Name**: {$persona->name}\n\n";

        if ($persona->background_story) {
            $prompt .= "**Background**: {$persona->background_story}\n\n";
        }

        if ($persona->personality_traits) {
            $prompt .= '**Personality**: '.$this->formatArray($persona->personality_traits)."\n\n";
        }

        if ($persona->expertise_areas) {
            $prompt .= '**Expertise**: '.$this->formatArray($persona->expertise_areas)."\n\n";
        }

        $prompt .= "**Voice Settings**:\n";
        $prompt .= '- Sentence Style: '.($persona->sentence_style ?? 'varied')."\n";
        $prompt .= '- Vocabulary: '.($persona->vocabulary_level ?? 'conversational')."\n";
        $prompt .= '- Tone: '.($persona->tone ?? 'warm')."\n";
        $prompt .= '- Formality: '.($persona->formality ?? 'balanced')."\n\n";

        if ($persona->catchphrases) {
            $prompt .= '**Catchphrases**: '.$this->formatArray($persona->catchphrases)."\n\n";
        }

        if ($persona->quirks) {
            $prompt .= '**Writing Quirks**: '.$this->formatArray($persona->quirks)."\n\n";
        }

        if ($persona->recurring_topics) {
            $prompt .= '**Often References**: '.$this->formatArray($persona->recurring_topics)."\n\n";
        }

        if ($persona->avoided_elements) {
            $prompt .= '**Never Does**: '.$this->formatArray($persona->avoided_elements)."\n\n";
        }

        return $prompt;
    }

    private function formatArray(?array $items): string
    {
        if (empty($items)) {
            return 'Not specified';
        }

        return implode(', ', $items);
    }

    private function formatVoiceBible(string $response, Author $persona): string
    {
        // Clean up the response
        $response = trim($response);

        // Add a header
        $formatted = "# Voice Bible: {$persona->name}\n\n";
        $formatted .= $response;

        return $formatted;
    }
}
