<?php

namespace App\Console\Commands;

use App\Contracts\AI\ImageGeneratorInterface;
use App\Contracts\AI\TextGeneratorInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class AiGenerateCommand extends Command
{
    protected $signature = 'ai:generate';

    protected $description = 'Generar contenido usando modelos de IA (texto o imagen)';

    public function __construct(
        private readonly TextGeneratorInterface $textGenerator,
        private readonly ImageGeneratorInterface $imageGenerator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🤖 Generador de Contenido con IA');
        $this->newLine();

        // Seleccionar tipo de generación
        $type = select(
            label: '¿Qué tipo de contenido deseas generar?',
            options: [
                'text' => '📝 Texto',
                'image' => '🖼️  Imagen',
            ],
            default: 'text'
        );

        if ($type === 'text') {
            return $this->handleTextGeneration();
        }

        return $this->handleImageGeneration();
    }

    private function handleTextGeneration(): int
    {
        $this->info('📝 Generación de Texto');
        $this->newLine();

        // Mostrar proveedor actual
        $this->comment('Proveedor: '.$this->textGenerator->getProviderName());
        $this->newLine();

        // Obtener el prompt
        $prompt = textarea(
            label: 'Ingresa tu prompt',
            placeholder: 'Escribe un artículo sobre cuidado de plantas...',
            required: true
        );

        // Opciones avanzadas
        $temperature = (float) text(
            label: 'Temperatura (0.0 - 2.0)',
            default: '0.7',
            validate: fn ($value) => is_numeric($value) && $value >= 0 && $value <= 2
                ? null
                : 'Debe ser un número entre 0.0 y 2.0'
        );

        $maxTokens = (int) text(
            label: 'Tokens máximos',
            default: '1000',
            validate: fn ($value) => is_numeric($value) && $value > 0
                ? null
                : 'Debe ser un número mayor a 0'
        );

        $this->newLine();
        $this->info('⏳ Generando contenido...');
        $this->newLine();

        try {
            $result = $this->textGenerator->generate($prompt, [
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->info('✨ Resultado:');
            $this->newLine();
            $this->line($result);
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            // Preguntar si desea guardar
            $save = select(
                label: '¿Deseas guardar el resultado?',
                options: ['yes' => 'Sí', 'no' => 'No'],
                default: 'no'
            );

            if ($save === 'yes') {
                $filename = text(
                    label: 'Nombre del archivo (sin extensión)',
                    default: 'text_'.now()->format('Y-m-d_His'),
                    required: true
                );

                $path = "ai-generated/text/{$filename}.txt";
                Storage::put($path, $result);

                $this->newLine();
                $this->info("✅ Guardado en: storage/app/{$path}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function handleImageGeneration(): int
    {
        $this->info('🖼️  Generación de Imagen');
        $this->newLine();

        // Mostrar proveedor actual
        $this->comment('Proveedor: '.$this->imageGenerator->getProviderName());
        $this->newLine();

        // Obtener el prompt
        $prompt = textarea(
            label: 'Ingresa tu prompt (en inglés para mejores resultados)',
            placeholder: 'A beautiful garden with colorful flowers...',
            required: true
        );

        // Opciones de dimensiones
        $size = select(
            label: 'Tamaño de la imagen',
            options: [
                '1024x1024' => '1024x1024 (Cuadrada)',
                '1024x1792' => '1024x1792 (Vertical)',
                '1792x1024' => '1792x1024 (Horizontal)',
                '1200x800' => '1200x800 (Blog)',
                'custom' => 'Personalizado',
            ],
            default: '1024x1024'
        );

        if ($size === 'custom') {
            $width = (int) text(
                label: 'Ancho (px)',
                default: '1024',
                validate: fn ($value) => is_numeric($value) && $value > 0
                    ? null
                    : 'Debe ser un número mayor a 0'
            );

            $height = (int) text(
                label: 'Alto (px)',
                default: '1024',
                validate: fn ($value) => is_numeric($value) && $value > 0
                    ? null
                    : 'Debe ser un número mayor a 0'
            );
        } else {
            [$width, $height] = explode('x', $size);
            $width = (int) $width;
            $height = (int) $height;
        }

        $this->newLine();
        $this->info('⏳ Generando imagen...');
        $this->newLine();

        try {
            $imageUrl = $this->imageGenerator->generate($prompt, [
                'width' => $width,
                'height' => $height,
            ]);

            $this->info('✅ Imagen generada exitosamente!');
            $this->newLine();
            $this->line("URL/Path: {$imageUrl}");
            $this->newLine();

            // Si es una URL local, mostrar la ruta completa
            if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $this->comment("Ruta completa: storage/app/{$imageUrl}");
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
