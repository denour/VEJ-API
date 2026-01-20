<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Author>
 */
class AuthorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'id' => (string) str()->ulid(),
            'name' => $name,
            'slug' => str($name)->slug(),
            'is_active' => true,
            'background_story' => fake()->realText(300),
            'personality_traits' => fake()->randomElements(
                ['enthusiastic', 'patient', 'methodical', 'creative', 'nurturing', 'analytical'],
                fake()->numberBetween(3, 5)
            ),
            'expertise_areas' => fake()->randomElements(
                ['tropical plants', 'succulents', 'organic gardening', 'composting', 'propagation'],
                fake()->numberBetween(2, 4)
            ),
            'sentence_style' => fake()->randomElement(['short', 'medium', 'varied', 'long']),
            'vocabulary_level' => fake()->randomElement(['simple', 'conversational', 'technical', 'academic']),
            'tone' => fake()->randomElement(['warm', 'neutral', 'authoritative', 'playful', 'nurturing']),
            'formality' => fake()->randomElement(['casual', 'balanced', 'formal']),
            'catchphrases' => [
                'As I always say...',
                'In my experience...',
                'Here\'s the thing...',
            ],
            'quirks' => [
                'Always relates plants to life lessons',
                'Uses seasonal metaphors',
            ],
            'recurring_topics' => [
                'Sustainable gardening',
                'Water conservation',
            ],
            'avoided_elements' => [
                'Never uses harsh chemicals',
                'Avoids complex jargon',
            ],
            'avatar_url' => fake()->imageUrl(200, 200, 'people'),
            'generation_stats' => [
                'posts_generated' => 0,
            ],
        ];
    }
}
