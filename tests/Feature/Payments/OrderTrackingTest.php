<?php

use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pedidoConTransferencia(): Order
{
    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    return Order::firstOrFail();
}

it('muestra el pedido con el enlace firmado', function () {
    $order = pedidoConTransferencia();

    $this->get($order->trackingUrl())
        ->assertOk()
        ->assertSee($order->code)
        ->assertSee('Estado del pago')
        // Instrucciones de la transferencia, generadas con la configuracion
        // vigente y no guardadas con el pedido.
        ->assertSee('Banco de prueba')
        ->assertSee('012345678901234567');
});

it('no deja consultar un pedido sin la firma', function () {
    $order = pedidoConTransferencia();

    // Sin firma, probar folios no sirve para ver pedidos ajenos.
    $this->get(route('orders.show', ['code' => $order->code], absolute: false))
        ->assertForbidden();
});

it('no deja consultar un pedido con una firma manipulada', function () {
    $order = pedidoConTransferencia();

    $url = $order->trackingUrl();

    $this->get(str_replace('signature=', 'signature=00', $url))->assertForbidden();
});

it('acepta un comprobante y lo deja pendiente de revision', function () {
    Storage::fake('local');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->image('deposito.jpg'),
    ])->assertRedirect();

    $receipt = PaymentReceipt::firstOrFail();

    expect($receipt->order_id)->toBe($order->id)
        ->and($receipt->status)->toBe(PaymentReceipt::STATUS_PENDING)
        ->and($receipt->original_name)->toBe('deposito.jpg')
        // Nombre aleatorio: el original puede traer datos de la persona y
        // permitiria adivinar otras direcciones.
        ->and($receipt->path)->not->toContain('deposito')
        ->and(Storage::disk('local')->exists($receipt->path))->toBeTrue();
});

it('guarda el comprobante en un disco privado', function () {
    Storage::fake('local');
    Storage::fake('public');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->image('deposito.jpg'),
    ]);

    // Un comprobante lleva datos bancarios: no debe quedar accesible con solo
    // adivinar su direccion.
    expect(Storage::disk('public')->allFiles())->toBeEmpty()
        ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
});

it('rechaza un archivo que no es comprobante', function () {
    Storage::fake('local');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
    ])->assertSessionHasErrors('receipt');

    expect(PaymentReceipt::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBeEmpty();
});

it('rechaza un comprobante demasiado grande', function () {
    Storage::fake('local');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->image('enorme.jpg')->size(6000),
    ])->assertSessionHasErrors('receipt');

    expect(PaymentReceipt::count())->toBe(0);
});

it('acepta un comprobante en PDF', function () {
    Storage::fake('local');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->create('deposito.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    expect(PaymentReceipt::count())->toBe(1);
});

it('no sirve un comprobante a visitantes anonimos', function () {
    Storage::fake('local');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->image('deposito.jpg'),
    ]);

    $receipt = PaymentReceipt::firstOrFail();

    // El acceso pasa por la autorizacion, no por adivinar la direccion.
    $this->get(route('receipts.show', ['receipt' => $receipt->getKey()]))
        ->assertRedirect();
});

it('lista los comprobantes enviados en la pagina del pedido', function () {
    Storage::fake('local');

    $order = pedidoConTransferencia();

    $this->post(route('receipts.store', ['code' => $order->code]), [
        'receipt' => UploadedFile::fake()->image('deposito.jpg'),
    ]);

    $this->get($order->trackingUrl())
        ->assertOk()
        ->assertSee('Comprobantes enviados')
        ->assertSee('Pendiente de revision');
});
