<?php

namespace Database\Seeders;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Shipping\Data\ShippingSettings;
use Illuminate\Database\Seeder;

/**
 * Siembra la configuracion inicial de envios.
 *
 * Solo escribe las claves que faltan: volver a sembrar no debe pisar lo que el
 * administrador haya ajustado desde el panel. Una vez sembrada, la fuente de
 * verdad es la configuracion administrable, no el archivo de entorno.
 */
class ShippingSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = new ShippingSettings(
            // Los valores del entorno solo sirven para el primer arranque, para
            // que una instalacion nueva pueda partir de otras cifras sin tocar
            // codigo.
            flatCents: (int) config('store.shipping_flat_cents', 9900),
            freeShippingThresholdCents: (int) config('store.free_shipping_threshold_cents', 80000),
        );

        app(SettingsRepository::class)->seedMissing(
            ShippingSettings::GROUP,
            $defaults->toArray(),
        );
    }
}
