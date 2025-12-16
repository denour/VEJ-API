<?php

namespace Database\Factories;

use App\Models\Author;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(6);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::random(4),
            'excerpt' => $this->faker->paragraph(),
            'content' => [
                [
                    'type' => 'paragraph',
                    'data' => ['text' => $this->faker->paragraphs(3, true)],
                ],
            ],
            'cover_image' => null,
            'category' => $this->faker->randomElement(['Cuidado de plantas', 'Plantas de sombra', 'Plagas']),
            'tags' => $this->faker->randomElements(['cuidado', 'riego', 'sombra', 'luz'], 2),
            'author_id' => Author::factory(),
            'list' => [
                ['id' => 'sec-1', 'text' => 'Introducción'],
            ],
            'published_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'reading_time' => $this->faker->numberBetween(3, 12),
            'featured' => $this->faker->boolean(20),
            'status' => $this->faker->randomElement(['draft', 'published']),
        ];
    }
}
