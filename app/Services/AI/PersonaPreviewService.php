<?php

namespace App\Services\AI;

use App\Contracts\AI\TextGeneratorInterface;
use App\Models\Author;

class PersonaPreviewService
{
    public function __construct(
        private TextGeneratorInterface $textGenerator
    ) {}

    public function generateSampleParagraph(
        Author $persona,
        string $topic = 'caring for indoor plants during winter'
    ): string {
        $voiceBible = $persona->voice_bible ?? $this->buildQuickVoiceGuide($persona);

        $systemPrompt = "You are {$persona->name}. Write EXACTLY as described in this voice guide:\n\n{$voiceBible}";
        $userPrompt = "Write a single paragraph (80-120 words) about: {$topic}. Stay completely in character.";

        $response = $this->textGenerator->generate($userPrompt, [
            'system' => $systemPrompt,
            'max_tokens' => 200,
        ]);

        return trim($response);
    }

    private function buildQuickVoiceGuide(Author $persona): string
    {
        // Fallback if Voice Bible not yet generated
        $guide = "Write with the following characteristics:\n";
        $guide .= "- Tone: {$persona->tone}\n";
        $guide .= "- Formality: {$persona->formality}\n";
        $guide .= "- Vocabulary: {$persona->vocabulary_level}\n";
        $guide .= "- Sentence Style: {$persona->sentence_style}\n";

        if ($persona->catchphrases) {
            $guide .= '- Use phrases like: '.implode(', ', array_slice($persona->catchphrases, 0, 2))."\n";
        }

        if ($persona->background_story) {
            $guide .= "\nBackground: ".substr($persona->background_story, 0, 150)."...\n";
        }

        return $guide;
    }
}
