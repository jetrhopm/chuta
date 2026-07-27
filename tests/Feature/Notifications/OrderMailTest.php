<?php

use App\Domain\Notifications\MailSettingsRepository;
use App\Domain\Payments\Actions\ReviewPaymentReceipt;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Mail\NewSaleMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentStatusMail;
use App\Mail\ReceiptReviewedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

function configurarCorreo(array $extra = []): void
{
    app(MailSettingsRepository::class)->save(array_merge([
        'enabled' => true,
        'host' => 'smtp.prueba.test',
        'port' => 587,
        'username' => 'usuario',
        'password' => 'secreto-smtp',
        'encryption' => 'tls',
        'from_address' => 'ventas@prueba.test',
        'from_name' => 'Tienda de prueba',
    ], $extra));
}

it('avisa al cliente cuando su pedido queda registrado', function () {
    Mail::fake();
    configurarCorreo();

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    Mail::assertQueued(OrderReceivedMail::class, function (OrderReceivedMail $mail): bool {
        return $mail->hasTo('cliente@example.test');
    });
});

it('el correo del pedido lleva el enlace de seguimiento y las instrucciones', function () {
    Mail::fake();
    configurarCorreo();

    $product = Product::factory()->withStock(10)->create(['price_cents' => 50000]);
    enviarCheckout([[$product, 1]]);

    $order = Order::firstOrFail();

    Mail::assertQueued(OrderReceivedMail::class, function (OrderReceivedMail $mail) use ($order): bool {
        // Sin el enlace firmado, un cliente sin cuenta no puede volver a su pedido.
        return str_contains($mail->order->trackingUrl(), 'signature=')
            && $mail->order->code === $order->code
            && str_contains((string) $mail->paymentInstructions, '012345678901234567');
    });
});

it('no intenta escribir a un pedido sin correo', function () {
    Mail::fake();
    configurarCorreo();

    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 1]], ['customer_email' => null])->assertSessionHasNoErrors();

    // El correo es opcional en el checkout: sin el no hay a donde escribir, pero
    // el pedido si se guarda.
    Mail::assertNotQueued(OrderReceivedMail::class);
    expect(Order::count())->toBe(1);
});

it('avisa a la direccion interna de cada venta nueva', function () {
    Mail::fake();
    configurarCorreo(['admin_notification_address' => 'ventas@interno.test']);

    $product = Product::factory()->withStock(10)->create();
    enviarCheckout([[$product, 1]]);

    Mail::assertQueued(NewSaleMail::class, fn (NewSaleMail $mail): bool => $mail->hasTo('ventas@interno.test'));
});

it('no manda aviso interno si no hay direccion configurada', function () {
    Mail::fake();
    configurarCorreo();

    $product = Product::factory()->withStock(10)->create();
    enviarCheckout([[$product, 1]]);

    Mail::assertNotQueued(NewSaleMail::class);
});

it('avisa al cliente cuando el pago cambia a un estado final', function () {
    Mail::fake();
    configurarCorreo();

    [$attempt, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_pago'])->assertOk();

    Mail::assertQueued(PaymentStatusMail::class, function (PaymentStatusMail $mail): bool {
        return $mail->status === PaymentStatus::Approved;
    });
});

it('no repite el aviso cuando llega dos veces el mismo cambio', function () {
    Mail::fake();
    configurarCorreo();

    [, $order] = pedidoConClip();

    fakeClip(['status' => 'paid', 'amount' => importeDe($order), 'currency' => 'MXN']);

    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_a'])->assertOk();
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_b'])->assertOk();

    // El segundo aviso no cambia el estado, asi que no debe generar otro correo.
    Mail::assertQueuedCount(2); // el de pedido recibido y el del pago
});

it('no avisa de estados intermedios', function () {
    Mail::fake();
    configurarCorreo();

    [, $order] = pedidoConClip();

    // Un importe que no cuadra deja el pago en revision, que es un estado
    // intermedio: recibir "procesando" no le dice nada util al cliente.
    fakeClip(['status' => 'paid', 'amount' => 1.00, 'currency' => 'MXN']);
    avisarClip(['payment_request_id' => 'pr_test', 'id' => 'evt_raro'])->assertOk();

    Mail::assertNotQueued(PaymentStatusMail::class);
});

it('avisa el resultado de la revision del comprobante', function () {
    Mail::fake();
    configurarCorreo();

    [$receipt] = pedidoConComprobante();

    app(ReviewPaymentReceipt::class)->reject(
        receipt: $receipt,
        reviewer: User::factory()->create(),
        comment: 'El comprobante no corresponde a este pedido',
    );

    Mail::assertQueued(ReceiptReviewedMail::class, function (ReceiptReviewedMail $mail): bool {
        // El motivo tiene que viajar: sin el, el cliente no sabe que corregir.
        return $mail->receipt->review_comment === 'El comprobante no corresponde a este pedido';
    });
});

it('guarda el pedido aunque el correo no se pueda encolar', function () {
    configurarCorreo();

    // Se simula una cola inaccesible: el pedido tiene que sobrevivir.
    Queue::shouldReceive('connection')->andThrow(new RuntimeException('cola caida'));

    $product = Product::factory()->withStock(10)->create();

    enviarCheckout([[$product, 1]])->assertSessionHasNoErrors();

    expect(Order::count())->toBe(1)
        ->and($product->fresh()->stock)->toBe(9);
});

it('los correos van en cola y no se envian en linea', function () {
    Mail::fake();
    configurarCorreo();

    $product = Product::factory()->withStock(10)->create();
    enviarCheckout([[$product, 1]]);

    // En cola: un servidor de correo lento no debe hacer esperar al cliente.
    Mail::assertQueued(OrderReceivedMail::class);
    Mail::assertNothingSent();
});
