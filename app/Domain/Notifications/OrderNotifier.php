<?php

namespace App\Domain\Notifications;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Mail\NewSaleMail;
use App\Mail\OrderReceivedMail;
use App\Mail\PaymentStatusMail;
use App\Mail\ReceiptReviewedMail;
use App\Models\Order;
use App\Models\PaymentReceipt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envia los correos de un pedido.
 *
 * Ninguna falla de correo puede tumbar una compra. Por eso cada envio va en cola y
 * ademas queda envuelto: si encolarlo falla —la base de la cola inaccesible, por
 * ejemplo—, se registra y la operacion de negocio sigue su curso. El cliente
 * preferira un pedido guardado sin correo que una compra perdida.
 */
class OrderNotifier
{
    public function orderReceived(Order $order, ?string $paymentInstructions = null): void
    {
        if ($order->customer_email !== null) {
            $this->send($order->customer_email, new OrderReceivedMail($order, $paymentInstructions), 'orderReceived');
        }

        $this->notifyAdmins($order);
    }

    public function paymentStatusChanged(Order $order, PaymentStatus $status): void
    {
        // Los estados intermedios no se avisan: recibir "procesando" no le dice
        // nada util a nadie y solo genera ruido.
        if (! $status->isFinal()) {
            return;
        }

        if ($order->customer_email === null) {
            return;
        }

        $this->send($order->customer_email, new PaymentStatusMail($order, $status), 'paymentStatusChanged');
    }

    public function receiptReviewed(PaymentReceipt $receipt): void
    {
        $email = $receipt->order?->customer_email;

        if ($email === null) {
            return;
        }

        $this->send($email, new ReceiptReviewedMail($receipt), 'receiptReviewed');
    }

    private function notifyAdmins(Order $order): void
    {
        $address = app(MailSettingsRepository::class)->get()->adminNotificationAddress;

        if ($address === '') {
            return;
        }

        $this->send($address, new NewSaleMail($order), 'newSale');
    }

    private function send(string $to, object $mailable, string $context): void
    {
        try {
            Mail::to($to)->queue($mailable);
        } catch (Throwable $exception) {
            // Queda constancia para poder reintentarlo, sin interrumpir la
            // operacion que disparo el aviso.
            Log::error('No se pudo encolar un correo de la tienda.', [
                'contexto' => $context,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
