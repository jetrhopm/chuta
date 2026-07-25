<?php

namespace App\Console\Commands;

use App\Domain\Media\Actions\StoreRemoteImage;
use App\Domain\Media\Exceptions\RemoteImageFailed;
use App\Domain\Storefront\StorefrontContentRepository;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trae al almacenamiento local las imagenes que todavia viven en un sitio
 * externo.
 *
 * Mientras el catalogo apunte a otro dominio, la tienda depende de que ese sitio
 * siga en pie: si deja de responder, los productos se quedan sin fotos. Este
 * comando descarga los archivos, los guarda en el disco publico y reescribe las
 * rutas en la base de datos.
 *
 * Es idempotente y se puede interrumpir: lo ya descargado se salta, asi que
 * volver a ejecutarlo continua donde se quedo.
 */
class LocalizeStoreMedia extends Command
{
    protected $signature = 'media:localize
        {--limit=0 : Procesa como maximo este numero de productos (0 = todos)}
        {--dry-run : Solo informa que se descargaria, sin escribir nada}';

    protected $description = 'Descarga a este servidor las imagenes que aun apuntan a un sitio externo';

    public function handle(StoreRemoteImage $store, StorefrontContentRepository $content): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo simulacion: no se descarga ni se escribe nada.');
        }

        $fallos = $this->localizeBanners($store, $content, $dryRun);
        $fallos += $this->localizeProducts($store, $dryRun);

        $this->newLine();

        $pendientes = $this->remoteProducts()->count();

        if ($pendientes > 0) {
            $this->warn("Quedan {$pendientes} productos apuntando a un sitio externo.");
        } else {
            $this->info('Ningun producto depende ya de un sitio externo.');
        }

        if ($fallos > 0) {
            // Se devuelve fallo para que un despliegue automatizado se entere,
            // pero lo descargado con exito ya quedo guardado.
            $this->error("{$fallos} imagenes no se pudieron descargar. Revisa el detalle de arriba.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function localizeProducts(StoreRemoteImage $store, bool $dryRun): int
    {
        $query = $this->remoteProducts();
        $limit = (int) $this->option('limit');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No hay imagenes de producto por descargar.');

            return 0;
        }

        $this->info("Descargando imagenes de {$total} productos.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $fallos = [];

        // Por trozos para no cargar miles de modelos en memoria de golpe. Se
        // recorre por id porque la consulta filtra justamente la columna que se
        // va a modificar, y sin un orden estable se saltarian filas.
        $query->orderBy('id')->chunkById(100, function ($products) use ($store, $dryRun, $bar, &$fallos): void {
            foreach ($products as $product) {
                $original = (string) $product->image_path;

                if ($dryRun) {
                    $bar->advance();

                    continue;
                }

                try {
                    $path = $store->handle($original, 'products');

                    $product->forceFill(['image_path' => $path])->saveQuietly();
                } catch (RemoteImageFailed $exception) {
                    // Se conserva la URL remota: dejarla es mejor que dejar al
                    // producto sin ninguna imagen.
                    $fallos[] = [$product->sku, $exception->getMessage()];
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($fallos !== []) {
            $this->table(['SKU', 'Motivo'], array_slice($fallos, 0, 25));

            if (count($fallos) > 25) {
                $this->line('... y '.(count($fallos) - 25).' mas.');
            }
        }

        return count($fallos);
    }

    private function localizeBanners(StoreRemoteImage $store, StorefrontContentRepository $content, bool $dryRun): int
    {
        $banners = $content->banners();
        $remotos = array_filter($banners, fn (array $b): bool => str_starts_with((string) ($b['image'] ?? ''), 'http'));

        if ($remotos === []) {
            $this->info('No hay banners por descargar.');

            return 0;
        }

        $this->info('Descargando '.count($remotos).' banners.');

        $fallos = 0;

        foreach ($banners as $index => $banner) {
            if (! str_starts_with((string) ($banner['image'] ?? ''), 'http')) {
                continue;
            }

            if ($dryRun) {
                continue;
            }

            try {
                $banners[$index]['image'] = $store->handle($banner['image'], 'banners');
            } catch (RemoteImageFailed $exception) {
                $this->warn("Banner: {$exception->getMessage()}");
                $fallos++;
            }
        }

        if (! $dryRun) {
            $content->saveBanners($banners);
        }

        return $fallos;
    }

    /**
     * @return Builder<Product>
     */
    private function remoteProducts()
    {
        return Product::query()->where('image_path', 'like', 'http%');
    }
}
