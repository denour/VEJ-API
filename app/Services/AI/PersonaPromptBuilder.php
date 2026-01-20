<?php

namespace App\Services\AI;

use App\Models\Author;

class PersonaPromptBuilder
{
    /**
     * Build a system prompt that embodies the persona.
     */
    public function buildSystemPrompt(Author $persona): string
    {
        $voiceBible = $persona->voice_bible;

        if (empty($voiceBible)) {
            return $this->buildFallbackPrompt($persona);
        }

        return <<<PROMPT
You are {$persona->name}, a gardening content writer.

=== YOUR VOICE BIBLE (Follow this exactly) ===
{$voiceBible}

=== CRITICAL RULES ===
1. NEVER break character
2. NEVER use phrases that aren't yours
3. ALWAYS maintain your unique voice throughout
4. Write as if you're having a conversation with a fellow plant lover
5. Stay true to your background, personality, and expertise
PROMPT;
    }

    /**
     * Enhance a content prompt with persona context.
     */
    public function enhanceContentPrompt(
        Author $persona,
        string $topic,
        string $contentType = 'blog_post'
    ): string {
        $expertise = $persona->expertise_areas
            ? implode(', ', $persona->expertise_areas)
            : 'gardening';

        $recurringTopics = $persona->recurring_topics
            ? implode(', ', $persona->recurring_topics)
            : '';

        $prompt = "Write a {$contentType} about: {$topic}\n\n";
        $prompt .= "As someone with expertise in {$expertise}, weave in your knowledge naturally.\n";

        if ($recurringTopics) {
            $prompt .= "If relevant, reference topics you often discuss: {$recurringTopics}\n";
        }

        if ($persona->catchphrases) {
            $prompt .= "\nRemember your catchphrases and use them sparingly but naturally:\n";
            $prompt .= '- '.implode("\n- ", array_slice($persona->catchphrases, 0, 3))."\n";
        }

        if ($persona->avoided_elements) {
            $prompt .= "\nThings to avoid:\n";
            $prompt .= '- '.implode("\n- ", array_slice($persona->avoided_elements, 0, 3))."\n";
        }

        return $prompt;
    }

    /**
     * Build a prompt for generating a specific paragraph type.
     */
    public function buildParagraphPrompt(
        Author $persona,
        string $description,
        string $context = ''
    ): string {
        $prompt = "Write a paragraph about: {$description}\n";

        if ($context) {
            $prompt .= "Context: {$context}\n";
        }

        $prompt .= "\nRemember: You are {$persona->name}. Stay in character.\n";

        if ($persona->sentence_style) {
            $prompt .= "Sentence style: {$persona->sentence_style}\n";
        }

        if ($persona->vocabulary_level) {
            $prompt .= "Vocabulary: {$persona->vocabulary_level}\n";
        }

        return $prompt;
    }

    /**
     * Build a prompt for generating a heading.
     */
    public function buildHeadingPrompt(
        Author $persona,
        string $description
    ): string {
        $prompt = "Create a heading for a section about: {$description}\n\n";
        $prompt .= "Make it {$persona->tone} and match your voice.\n";
        $prompt .= "Return ONLY the heading text, nothing else.\n";

        return $prompt;
    }

    /**
     * Build a prompt for generating a list.
     */
    public function buildListPrompt(
        Author $persona,
        string $description,
        bool $ordered = false
    ): string {
        $listType = $ordered ? 'numbered' : 'bulleted';
        $prompt = "Create a {$listType} list about: {$description}\n\n";
        $prompt .= "Write each item in your voice ({$persona->tone}, {$persona->formality}).\n";
        $prompt .= "Return as a JSON array of strings.\n";

        return $prompt;
    }

    /**
     * Build a prompt for generating a quote.
     */
    public function buildQuotePrompt(
        Author $persona,
        string $description
    ): string {
        $prompt = "Create an inspiring or insightful quote about: {$description}\n\n";
        $prompt .= "This should sound like something you ({$persona->name}) would say.\n";
        $prompt .= "Make it memorable and true to your personality.\n";
        $prompt .= "Return ONLY the quote text.\n";

        return $prompt;
    }

    /**
     * Get voice reminders for mid-generation checks.
     */
    public function getVoiceReminders(Author $persona): string
    {
        $reminders = "Voice Reminders:\n";
        $reminders .= "- Tone: {$persona->tone}\n";
        $reminders .= "- Formality: {$persona->formality}\n";
        $reminders .= "- You are: {$persona->name}\n";

        if ($persona->quirks) {
            $reminders .= '- Quirks: '.implode(', ', array_slice($persona->quirks, 0, 2))."\n";
        }

        return $reminders;
    }

    private function buildFallbackPrompt(Author $persona): string
    {
        $prompt = "You are {$persona->name}, a gardening writer.\n\n";

        if ($persona->background_story) {
            $backgroundSummary = substr($persona->background_story, 0, 200);
            $prompt .= "Background: {$backgroundSummary}...\n\n";
        }

        $prompt .= "Write with:\n";
        $prompt .= "- Tone: {$persona->tone}\n";
        $prompt .= "- Formality: {$persona->formality}\n";
        $prompt .= "- Vocabulary: {$persona->vocabulary_level}\n";
        $prompt .= "- Sentence Style: {$persona->sentence_style}\n\n";

        if ($persona->personality_traits) {
            $prompt .= 'Personality: '.implode(', ', array_slice($persona->personality_traits, 0, 3))."\n";
        }

        return $prompt;
    }
}
