<?php

namespace App\Console\Commands;

use App\Services\AI\SpeciesGeneratorService;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

class GenerateSpeciesCommand extends Command
{
    protected $signature = 'ai:generate-species {--title=}';

    protected $description = 'Generar una Especie automáticamente con IA a partir de un título';

    public function handle(SpeciesGeneratorService $service): int

    {
        $title = (string) ($this->option('title') ?? '');
        if ($title === '') {
            $title = text(
                label: 'Título / Nombre de la especie',
                placeholder: 'Ej. Ficus lyrata (higuera de hojas de violín)',
                required: true,
            );
        }

        $this->info('⏳ Generando especie con IA...');

        try {
            $species = $service->generate($title);

            $this->newLine();
            $this->info('✅ Especie generada');
            $this->line('ID: '.$species->id);
            $this->line('Nombre común: '.$species->common_name);
            if ($species->scientific_name) {
                $this->line('Nombre científico: '.$species->scientific_name);
            }
            $this->newLine();
            $this->comment('Puedes editarla en Filament en Ecommerce > Especies.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error al generar la especie: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
