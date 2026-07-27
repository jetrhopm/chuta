<?php

namespace App\Mail;

use App\Models\PaymentReceipt;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Resultado de la revision de un comprobante de transferencia.
 *
 * Cuando se rechaza, el correo lleva el motivo: sin el, el cliente no sabe que
 * corregir y solo puede volver a subir lo mismo.
 */
class ReceiptReviewedMail extends StoreMail
{
    public function __construct(public readonly PaymentReceipt $receipt)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        $accepted = $this->receipt->status === PaymentReceipt::STATUS_ACCEPTED;

        return new Envelope(
            subject: ($accepted ? 'Confirmamos tu pago' : 'Necesitamos otro comprobante')
                .' - Pedido '.$this->receipt->order->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.receipt-reviewed',
            with: [
                'receipt' => $this->receipt,
                'order' => $this->receipt->order,
                'accepted' => $this->receipt->status === PaymentReceipt::STATUS_ACCEPTED,
                'trackingUrl' => $this->receipt->order->trackingUrl(),
            ],
        );
    }
}
