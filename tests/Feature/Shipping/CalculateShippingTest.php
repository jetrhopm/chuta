<?php

use App\Domain\Shipping\Actions\CalculateShipping;
use App\Domain\Shipping\Data\ShippingSettings;
use App\Domain\Shipping\ShippingSettingsRepository;
use Database\Seeders\ShippingSettingsSeeder;

function guardarEnvios(ShippingSettings $settings): void
{
    app(ShippingSettingsRepository::class)->save($settings);
}

function cotizar(int $subtotalCents, int $descuentoCents = 0, ?string $estado = null, ?string $cp = null)
{
    return app(CalculateShipping::class)->handle($subtotalCents, $descuentoCents, $estado, $cp);
}

it('siembra la tarifa y el umbral que pide el documento', function () {
    $this->seed(ShippingSettingsSeeder::class);

    $settings = app(ShippingSettingsRepository::class)->get();

    expect($settings->flatCents)->toBe(9900)
        ->and($settings->freeShippingThresholdCents)->toBe(80000);
});

it('cobra la tarifa fija por debajo del umbral', function () {
    $this->seed(ShippingSettingsSeeder::class);

    $quote = cotizar(50000);

    expect($quote->costCents)->toBe(9900)
        ->and($quote->isFree)->toBeFalse()
        ->and($quote->remainingForFreeCents)->toBe(30000)
        ->and($quote->isAvailable())->toBeTrue();
});

it('da envio gratis justo al alcanzar el umbral', function () {
    $this->seed(ShippingSettingsSeeder::class);

    $quote = cotizar(80000);

    expect($quote->costCents)->toBe(0)
        ->and($quote->isFree)->toBeTrue()
        ->and($quote->remainingForFreeCents)->toBe(0);
});

it('descuenta del subtotal elegible cuando el umbral va despues de descuentos', function () {
    guardarEnvios(new ShippingSettings(thresholdAfterDiscounts: true));

    // 850 de subtotal con 100 de descuento deja 750 elegibles: no alcanza.
    $quote = cotizar(85000, 10000);

    expect($quote->isFree)->toBeFalse()
        ->and($quote->costCents)->toBe(9900)
        ->and($quote->remainingForFreeCents)->toBe(5000);
});

it('ignora los descuentos cuando el umbral va antes de descuentos', function () {
    guardarEnvios(new ShippingSettings(thresholdAfterDiscounts: false));

    // El mismo carrito de antes, pero ahora el descuento no resta para el umbral.
    $quote = cotizar(85000, 10000);

    expect($quote->isFree)->toBeTrue()
        ->and($quote->costCents)->toBe(0);
});

it('cobra siempre la tarifa cuando el envio gratis esta desactivado', function () {
    guardarEnvios(new ShippingSettings(freeShippingEnabled: false));

    $quote = cotizar(500000);

    expect($quote->costCents)->toBe(9900)
        ->and($quote->isFree)->toBeFalse()
        // Sin envio gratis activo no hay progreso que mostrar al cliente.
        ->and($quote->remainingForFreeCents)->toBe(0)
        ->and($quote->freeShippingMessage())->toBeNull();
});

it('avisa sin tecnicismos cuando los envios estan desactivados', function () {
    guardarEnvios(new ShippingSettings(enabled: false));

    $quote = cotizar(50000);

    expect($quote->isAvailable())->toBeFalse()
        ->and($quote->unavailableReason)->toBe('Por ahora no estamos realizando envios.');
});

it('rechaza un estado excluido sin importar acentos ni mayusculas', function () {
    guardarEnvios(new ShippingSettings(excludedStates: ['Michoacan']));

    expect(cotizar(50000, estado: 'MICHOACÁN')->isAvailable())->toBeFalse()
        ->and(cotizar(50000, estado: 'michoacan')->isAvailable())->toBeFalse()
        ->and(cotizar(50000, estado: 'Sonora')->isAvailable())->toBeTrue();
});

it('rechaza un codigo postal excluido', function () {
    guardarEnvios(new ShippingSettings(excludedPostcodes: ['85000', '01000']));

    expect(cotizar(50000, cp: '85000')->isAvailable())->toBeFalse()
        ->and(cotizar(50000, cp: '64000')->isAvailable())->toBeTrue();
});

it('acepta listas de exclusion capturadas como texto', function () {
    // El panel las captura separadas por coma o por salto de linea, que es lo
    // natural al pegar una lista.
    $settings = ShippingSettings::fromArray([
        'excluded_states' => "Michoacan, Guerrero\nTamaulipas",
        'excluded_postcodes' => '85000,  64000 ',
    ]);

    expect($settings->excludedStates)->toBe(['Michoacan', 'Guerrero', 'Tamaulipas'])
        ->and($settings->excludedPostcodes)->toBe(['85000', '64000']);
});

it('redacta el mensaje de progreso hacia el envio gratis', function () {
    $this->seed(ShippingSettingsSeeder::class);

    expect(cotizar(65000)->freeShippingMessage())->toBe('Te faltan $150.00 para obtener envio gratis.')
        ->and(cotizar(80000)->freeShippingMessage())->toBe('Ya tienes envio gratis.');
});

it('no pisa la configuracion ajustada al volver a sembrar', function () {
    $this->seed(ShippingSettingsSeeder::class);

    guardarEnvios(new ShippingSettings(flatCents: 12500, freeShippingThresholdCents: 100000));

    $this->seed(ShippingSettingsSeeder::class);

    $settings = app(ShippingSettingsRepository::class)->get();

    expect($settings->flatCents)->toBe(12500)
        ->and($settings->freeShippingThresholdCents)->toBe(100000);
});
