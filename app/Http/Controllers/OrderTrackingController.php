<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Data\PaymentRequestData;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Models\Order;
use Illuminate\Contracts\View\View;

/**
 * Consulta del pedido para clientes sin cuenta.
 *
 * La ruta va firmada, asi que el enlace solo funciona con la firma que emite este
 * servidor: nadie puede ver pedidos ajenos probando folios.
 */
class OrderTrackingController extends Controller
{
    public function __construct(private readonly PaymentGatewayRegistry $registry) {}

    public function show(string $code): View
    {
        $order = Order::with(['items', 'receipts'])
            ->where('code', $code)
            ->firstOrFail();

        $attempt = $order->currentPaymentAttempt();

        // Las instrucciones se regeneran con la configuracion vigente en lugar de
        // guardarse con el pedido, para que un cambio de CLABE no deje
        // instrucciones viejas circulando.
        $instructions = null;

        if ($attempt !== null && $attempt->provider === PaymentProvider::BankTransfer) {
            $instructions = $this->registry
                ->tryGet(PaymentProvider::BankTransfer)
                ?->createPayment(new PaymentRequestData(
                    order: $order,
                    amountCents: $order->total_cents,
                    currency: $attempt->currency,
                    description: 'Pedido '.$order->code,
                    idempotencyKey: $attempt->idempotency_key,
                    returnUrl: '',
                    cancelUrl: '',
                    webhookUrl: '',
                ))
                ->instructions;
        }

        return view('storefront.orders.show', [
            'order' => $order,
            'attempt' => $attempt,
            'instructions' => $instructions,
            'canUploadReceipt' => $attempt !== null
                && $attempt->provider === PaymentProvider::BankTransfer
                && ! $order->payment_status->isFinal(),
        ]);
    }
}
