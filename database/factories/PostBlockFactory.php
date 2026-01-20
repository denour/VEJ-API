<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\PostBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PostBlock>
 */
class PostBlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'type' => 'paragraph',
            'title' => null,
            'content' => fake()->paragraphs(3, true),
            'data' => null,
            'order' => 0,
        ];
    }

    public function paragraph(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'paragraph',
            'title' => null,
            'content' => fake()->paragraphs(rand(2, 4), true),
            'data' => null,
        ]);
    }

    public function heading(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'heading',
            'title' => fake()->sentence(rand(3, 6)),
            'content' => null,
            'data' => [
                'level' => rand(2, 3),
            ],
        ]);
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'title' => null,
            'content' => null,
            'data' => [
                'url' => fake()->imageUrl(800, 600),
                'alt' => fake()->sentence(),
                'caption' => fake()->optional()->sentence(),
            ],
        ]);
    }

    public function imagePending(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'title' => null,
            'content' => null,
            'data' => [
                'alt' => fake()->sentence(),
                'caption' => fake()->optional()->sentence(),
                'prompt' => fake()->sentence(),
            ],
        ]);
    }

    public function list(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'list',
            'title' => fake()->optional()->sentence(),
            'content' => null,
            'data' => [
                'items' => fake()->sentences(rand(3, 6)),
                'ordered' => fake()->boolean(),
            ],
        ]);
    }

    public function quote(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'quote',
            'title' => null,
            'content' => fake()->paragraph(),
            'data' => [
                'author' => fake()->optional()->name(),
                'source' => fake()->optional()->sentence(),
            ],
        ]);
    }

    public function code(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'code',
            'title' => fake()->optional()->sentence(),
            'content' => "function example() {\n    return 'Hello World';\n}",
            'data' => [
                'language' => fake()->randomElement(['javascript', 'php', 'python', 'html', 'css']),
                'filename' => fake()->optional()->word() . '.' . fake()->randomElement(['js', 'php', 'py', 'html', 'css']),
            ],
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'title' => fake()->optional()->sentence(),
            'content' => null,
            'data' => [
                'url' => 'https://www.youtube.com/watch?v=' . fake()->regexify('[A-Za-z0-9_-]{11}'),
                'provider' => 'youtube',
                'thumbnail' => fake()->imageUrl(800, 450),
                'caption' => fake()->optional()->sentence(),
            ],
        ]);
    }

    public function ordered(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }
}
