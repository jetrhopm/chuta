<?php

namespace App\Mail;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Aviso de cambio en el estado del pago.
 *
 * Un solo correo para todos los estados en lugar de uno por caso: el mensaje que
 * ve el cliente ya lo define el propio estado, asi que separarlos duplicaria
 * plantillas sin cambiar nada.
 */
class PaymentStatusMail extends StoreMail
{
    public function __construct(
        public readonly Order $order,
        public readonly PaymentStatus $status,
    ) {
        parent::__construct();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectFor().' - Pedido '.$this->order->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.orders.payment-status',
            with: [
                'order' => $this->order,
                'status' => $this->status,
                'trackingUrl' => $this->order->trackingUrl(),
            ],
        );
    }

    private function subjectFor(): string
    {
        return match ($this->status) {
            PaymentStatus::Approved => 'Tu pago quedo confirmado',
            PaymentStatus::Rejected => 'No pudimos confirmar tu pago',
            PaymentStatus::Cancelled => 'Tu pago se cancelo',
            PaymentStatus::Expired => 'El tiempo para pagar termino',
            PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => 'Procesamos tu reembolso',
            default => 'Actualizacion de tu pago',
        };
    }
}
