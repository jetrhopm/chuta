<?php

namespace App\Domain\Payments\Actions;

use App\Domain\Payments\Data\PaymentStatusResult;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\PaymentReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Revision de un comprobante de transferencia.
 *
 * Es el equivalente humano de un webhook: la transferencia no tiene proveedor al
 * que preguntar, asi que quien aprueba es una persona que vio el deposito. La
 * aprobacion pasa por la misma accion que liquida cualquier otro pago, para que
 * el pedido, el inventario y el historial queden igual que si lo hubiera
 * confirmado una pasarela.
 */
class ReviewPaymentReceipt
{
    public function __construct(private readonly SettlePayment $settlePayment) {}

    public function accept(PaymentReceipt $receipt, User $reviewer, ?string $comment = null): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt, $reviewer, $comment): PaymentReceipt {
            $receipt->forceFill([
                'status' => PaymentReceipt::STATUS_ACCEPTED,
                'reviewed_by' => $reviewer->getKey(),
                'reviewed_at' => now(),
                'review_comment' => $comment,
            ])->save();

            $order = $receipt->order;
            $attempt = $order?->currentPaymentAttempt();

            if ($attempt === null) {
                return $receipt;
            }

            // Se declara el importe del pedido a proposito: quien aprueba
            // confirmo que el deposito coincide, y asi la verificacion de importe
            // de SettlePayment se cumple con el dato que esa persona valido.
            $this->settlePayment->handle($attempt, new PaymentStatusResult(
                status: PaymentStatus::Approved,
                externalId: $attempt->external_id,
                amountCents: $attempt->amount_cents,
                currency: $attempt->currency,
                snapshot: [
                    'reviewed_by' => $reviewer->getKey(),
                    'receipt_id' => $receipt->getKey(),
                ],
            ));

            return $receipt;
        });
    }

    /**
     * Rechaza el comprobante sin cancelar el pedido.
     *
     * El pago se queda pendiente en lugar de rechazarse: lo normal es que el
     * cliente haya subido un archivo equivocado y pueda mandar otro, y cancelar
     * el pedido liberaria un inventario que quizas si esta vendido.
     */
    public function reject(PaymentReceipt $receipt, User $reviewer, string $comment): PaymentReceipt
    {
        $receipt->forceFill([
            'status' => PaymentReceipt::STATUS_REJECTED,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
            'review_comment' => $comment,
        ])->save();

        return $receipt;
    }
}
