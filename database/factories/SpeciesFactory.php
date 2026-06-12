<?php

namespace Database\Factories;

use App\Models\Species;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Species>
 */
class SpeciesFactory extends Factory
{
    protected $model = Species::class;

    public function definition(): array
    {
        return [
            'common_name' => $this->faker->words(2, true),
            'scientific_name' => $this->faker->words(2, true),
            'family' => $this->faker->optional()->word(),
            'origin' => $this->faker->optional()->country(),
            'description' => $this->faker->optional()->paragraphs(2, true),
            'care_level' => $this->faker->randomElement(['easy', 'medium', 'hard']),
            'sunlight' => $this->faker->randomElement(['low', 'medium', 'high']),
            'watering' => $this->faker->randomElement(['low', 'medium', 'high']),
            'image' => 'https://example.com/species/'.$this->faker->slug(2).'.png',
            'images' => [],
            'toxicity' => $this->faker->randomElement(['none', 'pets', 'humans', 'both']),
            'growth_rate' => $this->faker->randomElement(['slow', 'medium', 'fast']),
            'max_height_cm' => $this->faker->optional()->numberBetween(20, 400),
        ];
    }
}
