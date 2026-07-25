<?php

use App\Domain\Payments\Data\PaymentRequestData;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Gateways\ClipGateway;
use App\Domain\Payments\Settings\GatewaySettings;
use App\Models\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function configurarClip(array $extra = []): void
{
    app(GatewaySettings::class)->save(PaymentProvider::Clip, array_merge([
        'enabled' => true,
        'sandbox' => true,
        'api_key' => 'llave-de-prueba',
        'secret_key' => 'secreto-de-prueba',
        'webhook_secret' => 'secreto-webhook',
    ], $extra));
}

function pedidoDePrueba(int $totalCents = 129900): Order
{
    return Order::create([
        'code' => 'CHX-PAGO-'.strtoupper(substr(md5((string) mt_rand()), 0, 5)),
        'payment_method' => 'clip',
        'subtotal_cents' => $totalCents,
        'total_cents' => $totalCents,
        'customer_name' => 'Cliente Prueba',
        'customer_phone' => '6441234567',
        'shipping_street' => 'Calle Uno',
        'shipping_neighborhood' => 'Centro',
        'shipping_city' => 'Obregon',
        'shipping_state' => 'Sonora',
        'shipping_postcode' => '85000',
    ]);
}

function solicitudDePago(Order $order): PaymentRequestData
{
    return new PaymentRequestData(
        order: $order,
        amountCents: $order->total_cents,
        currency: 'MXN',
        description: 'Pedido '.$order->code,
        idempotencyKey: 'clave-'.$order->code,
        returnUrl: 'http://localhost/chuta/pago/retorno',
        cancelUrl: 'http://localhost/chuta/pago/cancelado',
        webhookUrl: 'http://localhost/chuta/webhooks/clip',
    );
}

it('no esta disponible sin credenciales', function () {
    expect(app(ClipGateway::class)->isAvailable())->toBeFalse();
});

it('no esta disponible si esta desactivado aunque tenga credenciales', function () {
    configurarClip(['enabled' => false]);

    expect(app(ClipGateway::class)->isAvailable())->toBeFalse();
});

it('crea el pago y devuelve la direccion a la que redirigir', function () {
    configurarClip();

    Http::fake([
        'api.payclip.com/v2/checkout' => Http::response([
            'payment_request_id' => 'pr_123',
            'payment_request_url' => 'https://pago.clip.mx/pr_123',
            'status' => 'pending',
            'amount' => 1299.00,
            'currency' => 'MXN',
        ], 200),
    ]);

    $result = app(ClipGateway::class)->createPayment(solicitudDePago(pedidoDePrueba()));

    expect($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->externalId)->toBe('pr_123')
        ->and($result->checkoutUrl)->toBe('https://pago.clip.mx/pr_123');
});

it('envia el importe en unidades y autentica con Basic', function () {
    configurarClip();

    Http::fake([
        '*' => Http::response(['payment_request_id' => 'pr_1', 'payment_request_url' => 'https://pago.clip.mx/pr_1'], 200),
    ]);

    app(ClipGateway::class)->createPayment(solicitudDePago(pedidoDePrueba(129900)));

    Http::assertSent(function (Request $request): bool {
        $esperado = 'Basic '.base64_encode('llave-de-prueba:secreto-de-prueba');

        return $request['amount'] === 1299.00
            && $request['currency'] === 'MXN'
            && $request->header('Authorization')[0] === $esperado;
    });
});

it('manda el folio del pedido para poder reconciliar el aviso', function () {
    configurarClip();

    Http::fake(['*' => Http::response(['payment_request_id' => 'pr_1', 'payment_request_url' => 'https://x.test'], 200)]);

    $order = pedidoDePrueba();
    app(ClipGateway::class)->createPayment(solicitudDePago($order));

    Http::assertSent(fn (Request $request): bool => $request['metadata']['order_code'] === $order->code);
});

it('rechaza el pago cuando el proveedor responde con error', function () {
    configurarClip();

    Http::fake(['*' => Http::response(['error' => 'invalid_amount'], 400)]);

    $result = app(ClipGateway::class)->createPayment(solicitudDePago(pedidoDePrueba()));

    expect($result->failed())->toBeTrue()
        // El mensaje va al cliente: nada de codigos ni nombres internos.
        ->and($result->failureReason)->not->toContain('invalid_amount')
        ->and($result->failureReason)->toContain('elige otro metodo');
});

it('rechaza el pago si la respuesta no trae enlace utilizable', function () {
    configurarClip();

    // Respuesta 200 pero sin URL: mandar al cliente a ninguna parte seria peor
    // que fallar aqui.
    Http::fake(['*' => Http::response(['status' => 'pending'], 200)]);

    expect(app(ClipGateway::class)->createPayment(solicitudDePago(pedidoDePrueba()))->failed())->toBeTrue();
});

it('traduce los estados del proveedor', function (string $remoto, PaymentStatus $esperado) {
    configurarClip();

    Http::fake(['*' => Http::response(['status' => $remoto, 'amount' => 1299.00, 'currency' => 'MXN'], 200)]);

    expect(app(ClipGateway::class)->queryPayment('pr_1')->status)->toBe($esperado);
})->with([
    ['paid', PaymentStatus::Approved],
    ['approved', PaymentStatus::Approved],
    ['pending', PaymentStatus::Pending],
    ['rejected', PaymentStatus::Rejected],
    ['cancelled', PaymentStatus::Cancelled],
    ['expired', PaymentStatus::Expired],
    ['refunded', PaymentStatus::Refunded],
    // Un estado desconocido no puede cerrar el pago por error.
    ['algo_nuevo', PaymentStatus::Processing],
]);

it('convierte el importe consultado a centavos', function () {
    configurarClip();

    Http::fake(['*' => Http::response(['status' => 'paid', 'amount' => 1299.00, 'currency' => 'mxn'], 200)]);

    $estado = app(ClipGateway::class)->queryPayment('pr_1');

    expect($estado->amountCents)->toBe(129900)
        ->and($estado->currency)->toBe('MXN')
        ->and($estado->matches(129900, 'MXN'))->toBeTrue()
        // Un importe distinto no debe pasar la comprobacion.
        ->and($estado->matches(99900, 'MXN'))->toBeFalse();
});

it('no da por rechazado un pago que no pudo consultar', function () {
    configurarClip();

    Http::fake(['*' => Http::response('servidor caido', 503)]);

    // Marcarlo como rechazado cancelaria pagos buenos: no saber no es lo mismo
    // que saber que fallo.
    expect(app(ClipGateway::class)->queryPayment('pr_1')->status)->toBe(PaymentStatus::Processing);
});

it('acepta un webhook con firma valida', function () {
    configurarClip();

    $cuerpo = json_encode(['payment_request_id' => 'pr_1', 'status' => 'paid']);
    $firma = hash_hmac('sha256', $cuerpo, 'secreto-webhook');

    expect(app(ClipGateway::class)->verifyWebhook($cuerpo, [], ['x-clip-signature' => [$firma]]))->toBeTrue();
});

it('rechaza un webhook con firma invalida', function () {
    configurarClip();

    $cuerpo = json_encode(['payment_request_id' => 'pr_1']);

    expect(app(ClipGateway::class)->verifyWebhook($cuerpo, [], ['x-clip-signature' => ['firma-inventada']]))->toBeFalse();
});

it('rechaza un webhook sin firma', function () {
    configurarClip();

    expect(app(ClipGateway::class)->verifyWebhook('{}', [], []))->toBeFalse();
});

it('rechaza un webhook si no hay secreto configurado', function () {
    configurarClip();
    app(GatewaySettings::class)->forget(PaymentProvider::Clip);

    $cuerpo = '{}';

    // Sin secreto no se puede verificar nada, y aceptarlo a ciegas permitiria a
    // cualquiera marcar pedidos como pagados.
    expect(app(ClipGateway::class)->verifyWebhook($cuerpo, [], ['x-clip-signature' => [hash_hmac('sha256', $cuerpo, 'x')]]))
        ->toBeFalse();
});

it('la prueba de conexion no genera ningun cobro', function () {
    configurarClip();

    Http::fake(['*' => Http::response(['error' => 'not_found'], 404)]);

    $result = app(ClipGateway::class)->testConnection();

    expect($result->successful)->toBeTrue();

    // Solo lecturas: ni una peticion que pudiera cobrar.
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
});

it('avisa cuando el proveedor rechaza las credenciales', function () {
    configurarClip();

    Http::fake(['*' => Http::response(['error' => 'unauthorized'], 401)]);

    $result = app(ClipGateway::class)->testConnection();

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('credenciales');
});

it('avisa cuando faltan credenciales sin llamar al proveedor', function () {
    Http::fake();

    $result = app(ClipGateway::class)->testConnection();

    expect($result->successful)->toBeFalse();
    Http::assertNothingSent();
});

it('no ofrece reembolsos si la cuenta no los tiene habilitados', function () {
    configurarClip();

    expect(app(ClipGateway::class)->supportsRefunds())->toBeFalse();

    configurarClip(['refunds_enabled' => true]);

    expect(app(ClipGateway::class)->supportsRefunds())->toBeTrue();
});
