<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso interno de una venta nueva.
 *
 * Va a la direccion que configure el administrador, no al cliente, y por eso
 * puede incluir el telefono y el metodo de pago para atenderla sin abrir el panel.
 */
class NewSaleMail extends StoreMail
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Venta nueva '.$this->order->code.' por '.$this->order->total,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.admin.new-sale',
            with: ['order' => $this->order],
        );
    }
}
