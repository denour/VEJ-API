<?php

namespace App\Console\Commands;

use App\Services\AI\ProductGeneratorService;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class GenerateProductCommand extends Command
{
    protected $signature = 'ai:generate-product {--title=}';

    protected $description = 'Generar un Producto automáticamente con IA a partir de un título';

    public function handle(ProductGeneratorService $service): int
    {
        $title = (string) ($this->option('title') ?? '');
        if ($title === '') {
            $title = text(
                label: 'Título del producto',
                placeholder: 'Ej. Monstera deliciosa en maceta de barro',
                required: true,
            );
        }

        $this->info('⏳ Generando producto con IA...');

        try {
            $product = $service->generate($title);

            $this->newLine();
            $this->info('✅ Producto generado');
            $this->line('ID: '.$product->id);
            $this->line('Nombre: '.$product->name);
            $this->line('Tipo: '.$product->type);
            if ($product->price !== null) {
                $this->line('Precio: '.$product->price.' '.$product->currency);
            }
            $this->newLine();
            $this->comment('Puedes editarlo en Filament en Ecommerce > Productos.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error al generar el producto: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
