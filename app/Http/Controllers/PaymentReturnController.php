<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\SettlePayment;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;

/**
 * Vuelta del cliente desde la pantalla del proveedor.
 *
 * Aqui no se cree nada de lo que llega en la URL. El navegador puede volver con
 * cualquier parametro, asi que el estado se pregunta al proveedor: marcar un
 * pedido como pagado por un parametro de retorno permitiria cobrar cero pesos
 * escribiendo la direccion a mano.
 */
class PaymentReturnController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly SettlePayment $settlePayment,
    ) {}

    public function returned(string $code): RedirectResponse
    {
        $order = Order::where('code', $code)->firstOrFail();

        $attempt = $order->currentPaymentAttempt();

        if ($attempt !== null && $attempt->external_id !== null) {
            $gateway = $this->registry->tryGet($attempt->provider);

            if ($gateway !== null) {
                // Consulta directa al proveedor: es la fuente de verdad del pago.
                $this->settlePayment->handle($attempt, $gateway->queryPayment($attempt->external_id));
            }
        }

        return redirect()->to($order->trackingUrl());
    }

    public function cancelled(string $code): RedirectResponse
    {
        $order = Order::where('code', $code)->firstOrFail();

        // Tampoco se cancela por confianza: quizas el cliente cerro la ventana pero
        // el cobro si entro. Se pregunta al proveedor igual que en el retorno.
        $attempt = $order->currentPaymentAttempt();

        if ($attempt !== null && $attempt->external_id !== null) {
            $gateway = $this->registry->tryGet($attempt->provider);

            if ($gateway !== null) {
                $this->settlePayment->handle($attempt, $gateway->queryPayment($attempt->external_id));
            }
        }

        return redirect()
            ->to($order->trackingUrl())
            ->with('payment_cancelled', true);
    }
}
