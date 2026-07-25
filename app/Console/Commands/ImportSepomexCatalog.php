<?php

namespace App\Console\Commands;

use App\Domain\Addresses\Importers\SepomexImporter;
use App\Domain\Addresses\PostalCodeLookup;
use App\Models\PostalCode;
use Illuminate\Console\Command;
use RuntimeException;

class ImportSepomexCatalog extends Command
{
    protected $signature = 'sepomex:import
        {path : Ruta del archivo del catalogo nacional descargado de Correos de Mexico}
        {--fresh : Vacia la tabla antes de importar en lugar de actualizar}';

    protected $description = 'Importa el catalogo nacional de codigos postales de Correos de Mexico';

    public function handle(SepomexImporter $importer, PostalCodeLookup $lookup): int
    {
        $path = $this->argument('path');

        if ($this->option('fresh')) {
            $this->warn('Vaciando la tabla de codigos postales.');
            PostalCode::query()->delete();
        }

        $this->info('Importando el catalogo. El archivo nacional pasa de las 145 mil filas, asi que puede tardar.');

        $bar = $this->output->createProgressBar();
        $bar->start();

        try {
            $imported = $importer->import($path, function (int $total) use ($bar): void {
                $bar->setProgress($total);
            });
        } catch (RuntimeException $exception) {
            $bar->finish();
            $this->newLine(2);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        // La cache guarda respuestas por codigo postal; despues de reimportar
        // quedaria sirviendo asentamientos viejos.
        $lookup->flushCache();

        $this->info(sprintf(
            'Listo: %s filas procesadas. La tabla tiene %s asentamientos en %s codigos postales.',
            number_format($imported),
            number_format(PostalCode::count()),
            number_format(PostalCode::distinct()->count('postcode')),
        ));

        return self::SUCCESS;
    }
}
