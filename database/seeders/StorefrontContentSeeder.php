<?php

namespace Database\Seeders;

use App\Domain\Storefront\StorefrontContentRepository;
use Illuminate\Database\Seeder;

/**
 * Siembra el contenido inicial de la portada.
 *
 * Solo escribe lo que falta: una vez sembrados, los banners viven en base de
 * datos y volver a sembrar no debe pisar los que ya se hayan descargado ni los
 * que se ajusten desde el panel.
 */
class StorefrontContentSeeder extends Seeder
{
    public function run(): void
    {
        app(StorefrontContentRepository::class)->seedDefaults();
    }
}
