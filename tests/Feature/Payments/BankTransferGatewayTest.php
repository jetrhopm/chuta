<?php

use App\Domain\Payments\Data\RefundRequestData;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Gateways\BankTransferGateway;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Domain\Payments\Settings\GatewaySettings;
use App\Models\PaymentAttempt;

function configurarTransferencia(array $extra = []): void
{
    app(GatewaySettings::class)->save(PaymentProvider::BankTransfer, array_merge([
        'enabled' => true,
        'bank' => 'BBVA',
        'account_holder' => 'Chutamax SA de CV',
        'clabe' => '012345678901234567',
        'expires_in_hours' => 48,
    ], $extra));
}

it('no esta disponible sin datos bancarios', function () {
    app(GatewaySettings::class)->save(PaymentProvider::BankTransfer, ['enabled' => true]);

    // Ofrecerlo sin CLABE dejaria al cliente sin saber a donde transferir.
    expect(app(BankTransferGateway::class)->isAvailable())->toBeFalse();
});

it('esta disponible con los datos completos', function () {
    configurarTransferencia();

    expect(app(BankTransferGateway::class)->isAvailable())->toBeTrue();
});

it('deja el pago pendiente con instrucciones y el folio como referencia', function () {
    configurarTransferencia();

    $order = pedidoDePrueba(150000);
    $result = app(BankTransferGateway::class)->createPayment(solicitudDePago($order));

    expect($result->status)->toBe(PaymentStatus::Pending)
        // El folio es lo que permite reconocer el deposito en el estado de cuenta.
        ->and($result->externalId)->toBe($order->code)
        ->and($result->checkoutUrl)->toBeNull()
        ->and($result->instructions)->toContain('BBVA')
        ->and($result->instructions)->toContain('012345678901234567')
        ->and($result->instructions)->toContain($order->code)
        ->and($result->instructions)->toContain('$1,500.00')
        ->and($result->instructions)->toContain('48 horas');
});

it('no dice soportar reembolsos por API', function () {
    configurarTransferencia();

    $gateway = app(BankTransferGateway::class);

    // Decir que si haria creer que el boton del panel devuelve el dinero.
    expect($gateway->supportsRefunds())->toBeFalse()
        ->and($gateway->refund(new RefundRequestData(
            attempt: new PaymentAttempt(['amount_cents' => 1000]),
            amountCents: 1000,
            currency: 'MXN',
        ))->successful)->toBeFalse();
});

it('nunca acepta un webhook', function () {
    configurarTransferencia();

    // Aceptar algo aqui permitiria marcar pedidos como pagados con una peticion
    // inventada, porque este metodo no recibe avisos de nadie.
    expect(app(BankTransferGateway::class)->verifyWebhook('{}', ['status' => 'paid'], []))->toBeFalse();
});

it('la prueba de conexion revisa que los datos esten completos', function () {
    app(GatewaySettings::class)->save(PaymentProvider::BankTransfer, ['enabled' => true, 'bank' => 'BBVA']);

    $result = app(BankTransferGateway::class)->testConnection();

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('beneficiario')
        ->and($result->message)->toContain('CLABE');
});

it('la prueba de conexion exige una CLABE de 18 digitos', function () {
    configurarTransferencia(['clabe' => '12345']);

    $result = app(BankTransferGateway::class)->testConnection();

    expect($result->successful)->toBeFalse()
        ->and($result->message)->toContain('18 digitos');
});

it('el registro solo ofrece los metodos configurados', function () {
    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->available())->toBeEmpty();

    configurarTransferencia();

    expect($registry->availableProviderValues())->toBe(['bank_transfer'])
        ->and($registry->isAvailable(PaymentProvider::BankTransfer))->toBeTrue()
        ->and($registry->isAvailable(PaymentProvider::Clip))->toBeFalse();
});

it('un metodo caido no arrastra a los demas', function () {
    configurarTransferencia();
    configurarClip(['api_key' => '', 'secret_key' => '']);

    // Clip queda fuera por falta de credenciales, pero la transferencia sigue
    // disponible.
    expect(app(PaymentGatewayRegistry::class)->availableProviderValues())->toBe(['bank_transfer']);
});

it('declara que Mercado Pago y PayPal aun no tienen adaptador', function () {
    $pendientes = array_map(
        fn (PaymentProvider $p): string => $p->value,
        app(PaymentGatewayRegistry::class)->pending(),
    );

    // Se expone para que el panel lo diga con claridad en lugar de mostrarlos
    // como si estuvieran listos.
    expect($pendientes)->toBe(['mercado_pago', 'paypal']);
});

it('cifra las credenciales y las devuelve enmascaradas al panel', function () {
    configurarClip();

    $settings = app(GatewaySettings::class);

    // En claro solo cuando hace falta llamar al proveedor.
    expect($settings->get(PaymentProvider::Clip, 'api_key'))->toBe('llave-de-prueba');

    // Enmascaradas para la interfaz: el panel muestra que hay una llave sin
    // devolverla completa al navegador.
    $display = $settings->forDisplay(PaymentProvider::Clip);

    expect($display['api_key'])->not->toBe('llave-de-prueba')
        ->and($display['api_key'])->toEndWith('ueba')
        ->and($display['api_key'])->toStartWith('****');
});

it('no guarda en claro las credenciales en la base de datos', function () {
    configurarClip();

    $guardado = DB::table('settings')
        ->where('group', 'payments.clip')
        ->where('key', 'api_key')
        ->value('value');

    expect((string) $guardado)->not->toContain('llave-de-prueba');
});

it('un secreto vacio no borra la credencial guardada', function () {
    configurarClip();

    // El panel envia los secretos enmascarados o vacios; guardar eso borraria la
    // credencial buena.
    app(GatewaySettings::class)->save(PaymentProvider::Clip, ['api_key' => '', 'sandbox' => false]);

    expect(app(GatewaySettings::class)->get(PaymentProvider::Clip, 'api_key'))->toBe('llave-de-prueba')
        ->and(app(GatewaySettings::class)->isSandbox(PaymentProvider::Clip))->toBeFalse();
});

it('al borrar la configuracion desactiva primero el metodo', function () {
    configurarClip();

    app(GatewaySettings::class)->forget(PaymentProvider::Clip);

    $settings = app(GatewaySettings::class);

    // Dejarlo activo sin credenciales lo ofreceria en el checkout para fallar
    // despues.
    expect($settings->isEnabled(PaymentProvider::Clip))->toBeFalse()
        ->and($settings->get(PaymentProvider::Clip, 'api_key'))->toBeNull()
        ->and($settings->hasSecret(PaymentProvider::Clip, 'secret_key'))->toBeFalse()
        ->and(app(PaymentGatewayRegistry::class)->isAvailable(PaymentProvider::Clip))->toBeFalse();
});

it('asume modo pruebas mientras no se diga lo contrario', function () {
    // Para cobrar de verdad hay que decirlo expresamente.
    expect(app(GatewaySettings::class)->isSandbox(PaymentProvider::Clip))->toBeTrue();
});
