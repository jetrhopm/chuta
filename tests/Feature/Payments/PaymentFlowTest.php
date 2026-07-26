<?php

use App\Domain\Payments\Actions\StartPayment;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentEvent;
use App\Models\Product;
use Illuminate\Support\Facades\Http;

/**
 * Un unico stub de HTTP para todo el ciclo de Clip.
 *
 * Se distingue por metodo en lugar de llamar a Http::fake() dos veces, porque los
 * stubs se acumulan: un segundo fake con el mismo patron no reemplaza al primero,
 * y la consulta de estado acabaria recibiendo la respuesta de creacion.
 *
 * @param  array<string, mixed>  $consulta  Lo que responde la consulta de estado.
 */
function fakeClip(array $consulta = []): void
{
    // La respuesta de la consulta se guarda en el contenedor y el stub la lee al
    // vuelo. Es necesario porque Http::fake() acumula: registrar un segundo stub
    // no reemplaza al primero, y este metodo se llama varias veces por prueba
    // para ir cambiando lo que contesta el proveedor.
    app()->instance('clip.respuesta', $consulta);

    if (app()->bound('clip.stub')) {
        return;
    }

    app()->instance('clip.stub', true);

    Http::fake(function ($request) {
        if ($request->method() === 'POST') {
            return Http::response([
                'payment_request_id' => 'pr_test',
                'payment_request_url' => 'https://pago.clip.mx/pr_test',
            ], 200);
        }

        return Http::response(app('clip.respuesta'), 200);
    });
}

function avisarClip(array $payload, ?string $firma = null)
{
    $cuerpo = json_encode($payload);

    return test()->call(
        'POST',
        route('payments.webhook', ['provider' => 'clip']),
        server: [
            'HTTP_X_CLIP_SIGNATURE' => $firma ?? hash_hmac('sha256', $cuerpo, 'secreto-webhook'),
            'CONTENT_TYPE' => 'application/json',
        ],
        content: $cuerpo,
    );
}

/**
 * Deja un pedido pagado con Clip a medias, listo para recibir avisos.
 *
 * @return array{0: PaymentAttempt, 1: Order, 2: Product}
 */
function pedidoConClip(int $cantidad = 1, int $precioCents = 50000): array
{
    configurarClip();
    fakeClip();

    $product = Product::factory()->withStock(10)->create(['price_cents' => $precioCents]);

    enviarCheckout([[$product, $cantidad]], ['payment_method' => 'clip'])->assertRedirect();

    $attempt = PaymentAttempt::firstOrFail();

    return [$attempt, $attempt->order, $product];
}

/**
 * Importe del pedido en unidades, tal como lo reporta el proveedor.
 */
function importeDe(Order $order): float
{
    return $order->total_cents / 100;
}

it('registra un intento de pago al confirmar el pedido', function () {
    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    $attempt = PaymentAttempt::firstOrFail();
    $order = Order::firstOrFail();

    expect($attempt->order_id)->toBe($order->id)
        ->and($attempt->provider)->toBe(PaymentProvider::BankTransfer)
        ->and($attempt->status)->toBe(PaymentStatus::Pending)
        // El importe del intento sale del pedido, no del formulario.
        ->and($attempt->amount_cents)->toBe($order->total_cents)
        ->and($attempt->idempotency_key)->not->toBeEmpty();
});

it('no genera dos cobros del mismo pedido con dos envios seguidos', function () {
    [$attempt, $order] = pedidoConClip();

    // Se vuelve a pedir el cobro del mismo pedido: debe reutilizar el intento.
    $segundo = app(StartPayment::class)->handle($order->fresh(), PaymentProvider::Clip);

    expect($segundo->id)->toBe($attempt->id)
        ->and(PaymentAttempt::count())->toBe(1);

    // Y solo se llamo una vez al proveedor para crear el cobro.
    Http::assertSentCount(1);
});

it('redirige al proveedor cuando el metodo cobra fuera de la tienda', function () {
    configurarClip();
    fakeClip();

    $product = Product::factory()->withStock(5)->create();

    enviarCheckout([[$product, 1]], ['payment_method' => 'clip'])
        ->assertRedirect('https://pago.clip.mx/pr_test');
});

it('aprueba el pago cuando el webhook trae firma valida y el importe cuadra', function () {
    [$attempt, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);

    // El webhook solo dice de que pago habla; el estado se pregunta al proveedor.
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_1', 'type' => 'payment.paid'])
        ->assertOk()
        ->assertJsonPath('processed', true);

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Approved)
        ->and($attempt->fresh()->paid_at)->not->toBeNull()
        ->and($order->fresh()->payment_status)->toBe(PaymentStatus::Approved)
        ->and($order->fresh()->status)->toBe('confirmed');
});

it('rechaza un webhook con firma invalida y no aprueba nada', function () {
    [$attempt, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);

    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_falso'], firma: 'firma-inventada');

    // Un aviso sin firma valida no puede marcar un pedido como pagado.
    expect($attempt->fresh()->status)->toBe(PaymentStatus::Pending);

    // Pero queda registrado, que es lo que sirve para investigarlo.
    $event = PaymentEvent::firstOrFail();

    expect($event->signature_valid)->toBeFalse()
        ->and($event->processed_at)->toBeNull();
});

it('procesa una sola vez el mismo aviso repetido', function () {
    [, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);

    $aviso = ['payment_request_id' => 'pr_test', 'id' => 'evt_repetido'];

    avisarClip($aviso)->assertOk();
    avisarClip($aviso)->assertOk();
    avisarClip($aviso)->assertOk();

    // La clave unica de (proveedor, evento) es lo que lo hace idempotente.
    expect(PaymentEvent::count())->toBe(1);
});

it('no aprueba un pago cobrado por menos de lo que vale el pedido', function () {
    [$attempt, $order] = pedidoConClip();

    // El proveedor dice "aprobado" pero por un peso.
    fakeClip(['status' => 'paid', 'amount' => 1.00, 'currency' => 'MXN']);

    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_corto'])->assertOk();

    // Ni aprobado ni rechazado: el dinero pudo entrar, asi que lo revisa una
    // persona.
    expect($attempt->fresh()->status)->toBe(PaymentStatus::Processing)
        ->and($attempt->fresh()->failure_reason)->toContain('revision manual')
        ->and($order->fresh()->status)->not->toBe('confirmed');
});

it('no aprueba un pago cobrado en otra moneda', function () {
    [$attempt, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'USD']);

    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_moneda'])->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Processing);
});

it('libera el inventario cuando el pago se rechaza', function () {
    [, $order, $product] = pedidoConClip(cantidad: 3);

    expect($product->fresh()->stock)->toBe(7);

    fakeClip(['status' => 'rejected', 'amount' => importeDe($order), 'currency' => 'MXN']);

    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_rech'])->assertOk();

    // Las piezas vuelven a estar a la venta y el pedido queda cancelado.
    expect($product->fresh()->stock)->toBe(10)
        ->and($order->fresh()->status)->toBe('cancelled')
        ->and(InventoryMovement::where('type', 'cancellation')->count())->toBe(1);
});

it('no reabre un pago ya aprobado con un aviso atrasado', function () {
    [$attempt, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_a'])->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Approved);

    // Llega tarde un aviso de "pendiente": no puede deshacer la aprobacion.
    fakeClip(['status' => 'pending', 'amount' => importeDe($order), 'currency' => 'MXN']);
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_b'])->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Approved);
});

it('si aplica un reembolso posterior a la aprobacion', function () {
    [$attempt, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_pago'])->assertOk();

    // Un reembolso llega despues de la aprobacion y si tiene que aplicarse.
    fakeClip(['status' => 'refunded', 'amount' => importeDe($order), 'currency' => 'MXN']);
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_reembolso'])->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentStatus::Refunded);
});

it('descarta un aviso de un pago que no existe aqui', function () {
    configurarClip();

    avisarClip(['payment_request_id' => 'pr_desconocido', 'id' => 'evt_x'])
        ->assertOk()
        ->assertJsonPath('processed', false);
});

it('devuelve 404 para un proveedor que no existe', function () {
    $this->postJson(route('payments.webhook', ['provider' => 'inventado']))->assertNotFound();
});

it('no marca pagado por el retorno del navegador', function () {
    [, $order] = pedidoConClip();

    // El proveedor dice que sigue pendiente, aunque el navegador vuelva "bien".
    fakeClip(['status' => 'pending', 'amount' => importeDe($order), 'currency' => 'MXN']);

    $this->get(route('payments.return', ['code' => $order->code]).'?status=approved&paid=1')
        ->assertRedirect();

    // Escribir la direccion a mano no puede aprobar un pago.
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});

it('consulta al proveedor tambien cuando el cliente cancela', function () {
    [, $order] = pedidoConClip();

    // Quizas cerro la ventana pero el cobro si entro, asi que se pregunta igual.
    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);

    $this->get(route('payments.cancelled', ['code' => $order->code]))->assertRedirect();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Approved);
});
