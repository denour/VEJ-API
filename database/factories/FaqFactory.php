<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    public function definition(): array
    {
        return [
            'category' => $this->faker->randomElement(['Compras', 'Envíos', 'Garantías']),
            'question' => $this->faker->sentence(8),
            'answer' => $this->faker->paragraphs(2, true),
        ];
    }
}
