<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\AuthorTopic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuthorTopic>
 */
class AuthorTopicFactory extends Factory
{
    protected $model = AuthorTopic::class;

    public function definition(): array
    {
        $categories = ['Cuidado', 'Identificación', 'Decoración', 'Herramientas', 'Consejos'];

        $topics = [
            'Cómo revivir una planta marchita',
            'Plantas resistentes a la sequía',
            'Guía de poda para principiantes',
            'Compostaje casero paso a paso',
            'Plantas que purifican el aire',
            'Calendario de siembra mensual',
            'Fertilizantes orgánicos caseros',
            'Propagación por esquejes',
            'Jardín vertical en espacios pequeños',
            'Control natural de plagas',
        ];

        return [
            'author_id' => Author::factory(),
            'topic' => fake()->randomElement($topics),
            'category' => fake()->randomElement($categories),
            'used_at' => null,
            'post_id' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn () => [
            'used_at' => fake()->dateTimeBetween('-30 days'),
        ]);
    }
}
