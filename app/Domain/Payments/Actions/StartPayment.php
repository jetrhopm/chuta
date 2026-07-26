<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Data\PaymentRequestData;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pide el cobro de un pedido y deja constancia del intento.
 *
 * El intento se registra antes de llamar al proveedor: si la llamada se corta a
 * medias, queda el rastro de que se pidio un cobro y con que clave, en lugar de
 * un pedido sin explicacion.
 */
class StartPayment
{
    public function __construct(private readonly PaymentGatewayRegistry $registry) {}

    public function handle(Order $order, PaymentProvider $provider): PaymentAttempt
    {
        $gateway = $this->registry->get($provider);

        // Idempotencia: si ya hay un intento vivo para este pedido y proveedor se
        // reutiliza, para que un doble clic o un reintento de la red no generen
        // dos cobros del mismo pedido.
        $existing = $this->reusableAttempt($order, $provider);

        if ($existing !== null) {
            return $existing;
        }

        $attempt = PaymentAttempt::create([
            'order_id' => $order->getKey(),
            'provider' => $provider,
            'status' => PaymentStatus::Pending,
            'amount_cents' => $order->total_cents,
            'currency' => config('store.currency', 'MXN'),
            'idempotency_key' => $this->idempotencyKey($order, $provider),
            'sandbox' => $gateway->isSandbox(),
        ]);

        $data = new PaymentRequestData(
            order: $order,
            amountCents: $attempt->amount_cents,
            currency: $attempt->currency,
            description: 'Pedido '.$order->code,
            idempotencyKey: $attempt->idempotency_key,
            returnUrl: route('payments.return', ['code' => $order->code]),
            cancelUrl: route('payments.cancelled', ['code' => $order->code]),
            webhookUrl: route('payments.webhook', ['provider' => $provider->value]),
        );

        $result = $gateway->createPayment($data);

        $attempt->forceFill([
            'status' => $result->status,
            'external_id' => $result->externalId,
            'checkout_url' => $result->checkoutUrl,
            'failure_reason' => $result->failureReason,
            'response_snapshot' => $result->snapshot,
            'request_snapshot' => [
                // Solo lo necesario para aclarar una diferencia con el proveedor.
                // Nada de datos del pagador ni de la tarjeta.
                'amount_cents' => $attempt->amount_cents,
                'currency' => $attempt->currency,
                'order_code' => $order->code,
            ],
        ])->save();

        // Las instrucciones no se guardan en el intento: se generan a partir de la
        // configuracion vigente cada vez que se muestran, para que un cambio de
        // CLABE no deje instrucciones viejas circulando.
        $attempt->setAttribute('instructions', $result->instructions);

        if ($result->failed()) {
            $order->forceFill(['payment_status' => PaymentStatus::Rejected])->save();
        } else {
            $order->forceFill([
                'payment_method' => $provider->value,
                'payment_status' => $result->status,
            ])->save();
        }

        return $attempt;
    }

    /**
     * Intento anterior que todavia sirve para cobrar.
     *
     * Solo se reutiliza si sigue abierto. Uno rechazado o expirado no vale: el
     * cliente tiene derecho a volver a intentarlo.
     */
    private function reusableAttempt(Order $order, PaymentProvider $provider): ?PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('order_id', $order->getKey())
            ->where('provider', $provider->value)
            ->whereIn('status', [PaymentStatus::Pending->value, PaymentStatus::Processing->value])
            ->latest('id')
            ->first();
    }

    private function idempotencyKey(Order $order, PaymentProvider $provider): string
    {
        return DB::transaction(fn (): string => sprintf(
            '%s-%s-%s',
            $provider->value,
            $order->code,
            Str::lower(Str::random(10)),
        ));
    }
}
