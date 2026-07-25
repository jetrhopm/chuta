<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Ayudantes compartidos
|--------------------------------------------------------------------------
|
| Viven aqui y no dentro de un archivo de prueba: una funcion declarada en un
| archivo y usada desde otro depende del orden en que Pest los cargue.
|
*/

/**
 * Envia el formulario de checkout con datos validos.
 *
 * Solo se pasan las lineas del carrito; el resto de los campos son datos de
 * relleno que ya cumplen las validaciones, para que cada prueba se concentre en
 * lo que de verdad quiere comprobar.
 *
 * @param  array<int, array{0: Product, 1: int}>  $lineas
 * @param  array<string, mixed>  $sobreescribe
 */
function enviarCheckout(array $lineas, array $sobreescribe = []): TestResponse
{
    $payload = array_map(
        fn (array $linea): array => ['id' => $linea[0]->id, 'quantity' => $linea[1]],
        $lineas,
    );

    return test()->post('/checkout', array_merge([
        'cart_payload' => json_encode($payload),
        'customer_name' => 'Cliente Prueba',
        'customer_email' => 'cliente@example.test',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle Uno',
        'shipping_number' => '123',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
        'payment_method' => 'bank_transfer',
    ], $sobreescribe));
}
