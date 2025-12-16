<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['sale', 'trade', 'free']);
        $price = $type === 'sale' ? $this->faker->randomFloat(2, 50, 1500) : null;

        return [
            'image' => null,
            'images' => [],
            'name' => $this->faker->words(3, true),
            'scientific_name' => $this->faker->words(2, true),
            'care_level' => $this->faker->randomElement(['easy', 'medium', 'hard']),
            'sunlight' => $this->faker->randomElement(['low', 'medium', 'high']),
            'watering' => $this->faker->randomElement(['low', 'medium', 'high']),
            'condition' => $this->faker->randomElement(['seedling', 'young', 'mature']),
            'size' => $this->faker->randomElement(['small', 'medium', 'large', 'xl']),
            'is_rare' => $this->faker->boolean(10),
            'type' => $type,
            'price' => $price,
            'currency' => 'MXN',
            'rating' => $this->faker->randomFloat(2, 0, 5),
            'reviews' => $this->faker->numberBetween(0, 500),
            'in_stock' => $this->faker->boolean(80),
            'quantity' => $this->faker->optional()->numberBetween(0, 100),
        ];
    }
}
