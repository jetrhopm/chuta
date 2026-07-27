<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Confirmacion de que el pedido quedo registrado.
 *
 * Lleva el enlace firmado de seguimiento: es la unica forma de que un cliente sin
 * cuenta pueda volver a su pedido despues de cerrar el navegador.
 */
class OrderReceivedMail extends StoreMail
{
    public function __construct(
        public readonly Order $order,
        public readonly ?string $paymentInstructions = null,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recibimos tu pedido '.$this->order->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.received',
            with: [
                'order' => $this->order,
                'instructions' => $this->paymentInstructions,
                'trackingUrl' => $this->order->trackingUrl(),
            ],
        );
    }
}
