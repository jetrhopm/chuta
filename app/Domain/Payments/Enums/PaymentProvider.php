<?php

namespace App\Domain\Payments\Enums;

enum PaymentProvider: string
{
    case Clip = 'clip';
    case MercadoPago = 'mercado_pago';
    case PayPal = 'paypal';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Clip => 'Clip',
            self::MercadoPago => 'Mercado Pago',
            self::PayPal => 'PayPal',
            self::BankTransfer => 'Transferencia bancaria',
        };
    }

    /**
     * Grupo de configuracion donde viven sus credenciales.
     */
    public function settingsGroup(): string
    {
        return 'payments.'.$this->value;
    }

    /**
     * Si el cobro ocurre fuera de la tienda.
     *
     * Los proveedores redireccionados mandan al cliente a su propia pantalla; la
     * transferencia se resuelve con instrucciones y un comprobante.
     */
    public function isRedirect(): bool
    {
        return match ($this) {
            self::Clip, self::PayPal => true,
            self::MercadoPago => false,
            self::BankTransfer => false,
        };
    }

    /**
     * Si el proveedor puede confirmar el pago por su cuenta.
     *
     * La transferencia no: la aprueba una persona al revisar el comprobante.
     */
    public function confirmsAutomatically(): bool
    {
        return $this !== self::BankTransfer;
    }
}
