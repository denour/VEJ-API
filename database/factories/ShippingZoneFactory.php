<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    protected $model = ShippingZone::class;

    public function definition(): array
    {
        return [
            'zone' => $this->faker->randomElement(['Local', 'Nacional', 'Internacional']),
            'time' => $this->faker->randomElement(['24-48h', '3-5 días', '7-14 días']),
            'cost' => $this->faker->randomFloat(2, 50, 500),
            'regular_cost' => $this->faker->optional()->randomFloat(2, 60, 600),
            'currency' => 'MXN',
        ];
    }
}
