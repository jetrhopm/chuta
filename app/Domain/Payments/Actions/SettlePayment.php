<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Inventory\Actions\RestockOrder;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Notifications\OrderNotifier;
use App\Domain\Payments\Data\PaymentStatusResult;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Aplica al pedido el estado que reporta el proveedor.
 *
 * Es el unico camino por el que un pago se da por bueno, y comprueba el importe
 * y la moneda antes de hacerlo: un estado "aprobado" con un importe menor al del
 * pedido no aprueba nada. Sin esa comprobacion, manipular el cobro para pagar
 * menos dejaria el pedido igualmente confirmado.
 */
class SettlePayment
{
    public function __construct(
        private readonly RestockOrder $restockOrder,
        private readonly OrderNotifier $notifier,
    ) {}

    public function handle(PaymentAttempt $attempt, PaymentStatusResult $result): PaymentAttempt
    {
        $estadoAnterior = $attempt->status;

        $attempt = $this->apply($attempt, $result);

        // El aviso sale fuera de la transaccion y solo si el estado cambio de
        // verdad: un webhook repetido no debe volver a escribirle al cliente.
        if ($attempt->status !== $estadoAnterior && $attempt->order !== null) {
            $this->notifier->paymentStatusChanged($attempt->order, $attempt->status);
        }

        return $attempt;
    }

    private function apply(PaymentAttempt $attempt, PaymentStatusResult $result): PaymentAttempt
    {
        return DB::transaction(function () use ($attempt, $result): PaymentAttempt {
            // Se relee con la fila bloqueada porque el mismo pago puede llegar por
            // el webhook y por el retorno del navegador casi al mismo tiempo.
            $attempt = PaymentAttempt::query()
                ->whereKey($attempt->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Un estado final no se reabre: un aviso atrasado no puede deshacer un
            // reembolso ni resucitar un pago cancelado.
            if ($attempt->status->isFinal() && ! $this->isEscalation($attempt->status, $result->status)) {
                return $attempt;
            }

            $status = $result->status;

            if ($status === PaymentStatus::Approved && ! $this->amountIsCorrect($attempt, $result)) {
                Log::warning('Un pago aprobado no cuadra con el importe del pedido.', [
                    'payment_attempt_id' => $attempt->getKey(),
                    'esperado_centavos' => $attempt->amount_cents,
                    'reportado_centavos' => $result->amountCents,
                    'moneda_esperada' => $attempt->currency,
                    'moneda_reportada' => $result->currency,
                ]);

                // Se deja en revision en lugar de aprobarlo o rechazarlo: el dinero
                // pudo entrar de verdad, asi que lo decide una persona.
                $attempt->forceFill([
                    'status' => PaymentStatus::Processing,
                    'failure_reason' => 'El importe cobrado no coincide con el del pedido. Requiere revision manual.',
                    'response_snapshot' => $result->snapshot,
                ])->save();

                $this->syncOrder($attempt, PaymentStatus::Processing);

                return $attempt;
            }

            $attempt->forceFill([
                'status' => $status,
                'external_id' => $result->externalId ?? $attempt->external_id,
                'failure_reason' => $result->failureReason,
                'response_snapshot' => $result->snapshot,
                'paid_at' => $status === PaymentStatus::Approved ? ($attempt->paid_at ?? now()) : $attempt->paid_at,
            ])->save();

            $this->syncOrder($attempt, $status);

            return $attempt;
        });
    }

    private function amountIsCorrect(PaymentAttempt $attempt, PaymentStatusResult $result): bool
    {
        // Un proveedor que no informa importe no permite verificar nada. Se acepta
        // solo porque el estado viene de una consulta directa al proveedor y no del
        // navegador, pero queda anotado para poder auditarlo.
        if ($result->amountCents === null || $result->currency === null) {
            Log::info('El proveedor no informo importe; el pago se acepta sin verificar la cifra.', [
                'payment_attempt_id' => $attempt->getKey(),
                'provider' => $attempt->provider->value,
            ]);

            return true;
        }

        return $result->matches($attempt->amount_cents, $attempt->currency);
    }

    /**
     * Si el estado nuevo debe sobrescribir uno que ya era final.
     *
     * Un reembolso o un contracargo llegan despues de la aprobacion y si tienen
     * que aplicarse; lo que no puede pasar es volver de un reembolso a aprobado.
     */
    private function isEscalation(PaymentStatus $current, PaymentStatus $next): bool
    {
        if ($current !== PaymentStatus::Approved) {
            return false;
        }

        return in_array($next, [
            PaymentStatus::PartiallyRefunded,
            PaymentStatus::Refunded,
            PaymentStatus::Chargeback,
        ], strict: true);
    }

    private function syncOrder(PaymentAttempt $attempt, PaymentStatus $status): void
    {
        $order = $attempt->order;

        if ($order === null) {
            return;
        }

        $updates = ['payment_status' => $status];

        if ($status === PaymentStatus::Approved && $order->status === 'pending_confirmation') {
            $updates['status'] = 'confirmed';
        }

        // Un pago que ya no va a completarse libera el inventario que el pedido
        // habia descontado, para que esas piezas vuelvan a estar a la venta.
        $liberaInventario = in_array($status, [
            PaymentStatus::Rejected,
            PaymentStatus::Cancelled,
            PaymentStatus::Expired,
        ], strict: true);

        if ($liberaInventario) {
            $updates['status'] = 'cancelled';
        }

        $order->forceFill($updates)->save();

        if ($liberaInventario) {
            // La reposicion es controlada: solo devuelve lo que de verdad se
            // habia descontado y nunca dos veces el mismo pedido.
            $this->restockOrder->handle(
                order: $order->load('items.product'),
                type: InventoryMovementType::Cancellation,
                reason: 'Pago '.$status->label(),
            );
        }
    }
}
