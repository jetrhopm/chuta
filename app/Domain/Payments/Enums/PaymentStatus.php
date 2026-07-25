<?php

namespace App\Domain\Payments\Enums;

/**
 * Estado del pago, independiente del estado del pedido.
 *
 * Son dos cosas distintas a proposito: un pedido puede estar en preparacion con
 * el pago aprobado, o entregado con un contracargo posterior.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Chargeback = 'chargeback';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Processing => 'Procesando',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Cancelled => 'Cancelado',
            self::Expired => 'Expirado',
            self::PartiallyRefunded => 'Reembolsado parcialmente',
            self::Refunded => 'Reembolsado',
            self::Chargeback => 'Contracargo',
        };
    }

    /**
     * Mensaje para el cliente. Nunca expone codigos ni nombres internos.
     */
    public function customerMessage(): string
    {
        return match ($this) {
            self::Pending => 'Estamos esperando tu pago.',
            self::Processing => 'Estamos confirmando tu pago.',
            self::Approved => 'Tu pago quedo confirmado.',
            self::Rejected => 'No pudimos comprobar el pago. Intenta nuevamente o elige otro metodo.',
            self::Cancelled => 'El pago se cancelo.',
            self::Expired => 'El tiempo para pagar se termino. Puedes generar el pago de nuevo.',
            self::PartiallyRefunded => 'Te reembolsamos una parte de tu compra.',
            self::Refunded => 'Te reembolsamos tu compra.',
            self::Chargeback => 'Hay una aclaracion en curso con tu banco.',
        };
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Pending, self::Processing => false,
            default => true,
        };
    }

    /**
     * Si el estado autoriza a surtir el pedido.
     */
    public function releasesOrder(): bool
    {
        return $this === self::Approved || $this === self::PartiallyRefunded;
    }
}
