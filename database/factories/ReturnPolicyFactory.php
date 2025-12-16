<?php

namespace Database\Factories;

use App\Models\ReturnPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReturnPolicy>
 */
class ReturnPolicyFactory extends Factory
{
    protected $model = ReturnPolicy::class;

    public function definition(): array
    {
        return [
            'title' => 'Política de devoluciones',
            'guarantee_reasons' => [
                ['title' => 'Llegó dañada', 'description' => 'Tu planta llegó en mal estado', 'icon' => 'heroicon-o-shield-check'],
            ],
            'return_steps' => [
                ['number' => 1, 'title' => 'Contacta', 'description' => 'Escríbenos en 48h', 'icon' => 'heroicon-o-chat-bubble-oval-left-ellipsis'],
            ],
            'conditions' => ['Conservar empaque original'],
            'quick_cards' => [
                ['icon' => 'heroicon-o-truck', 'title' => 'Envíos rápidos', 'description' => '24-48h', 'bg' => '#F0FDF4', 'iconColor' => '#16A34A'],
            ],
        ];
    }
}
