<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Services\AI\PostGeneratorService;
use Illuminate\Console\Command;

class GenerateDailyPost extends Command
{
    protected $signature = 'posts:generate-daily';

    protected $description = 'Generate a daily blog post about gardening using AI';

    public function __construct(
        private readonly PostGeneratorService $postGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🌱 Generando post diario con IA...');

        try {
            $author = Author::query()->inRandomOrder()->first(); // o por id, etc.
            $post = $this->postGenerator->generatePost($author);

            $this->newLine();
            $this->info('✅ Post generado exitosamente!');
            $this->line("📝 Título: {$post->title}");
            $this->line("🔖 Categoría: {$post->category}");
            $this->line("📊 Estado: {$post->status}");
            $this->line("🆔 ID: {$post->id}");
            $this->line("⏱️  Tiempo de lectura: {$post->reading_time} min");
            $this->newLine();

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error al generar el post:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }
}
