<?php

namespace App\Domain\Promotions\Enums;

enum DiscountType: string
{
    case FixedAmount = 'fixed_amount';
    case Percentage = 'percentage';
    case FreeShipping = 'free_shipping';
    /**
     * Compra X y recibe Y gratis. Cubre 2x1 (comprar 2, uno gratis) y 3x2
     * (comprar 3, uno gratis) con el mismo calculo.
     */
    case BuyXGetY = 'buy_x_get_y';

    public function label(): string
    {
        return match ($this) {
            self::FixedAmount => 'Descuento de monto fijo',
            self::Percentage => 'Descuento porcentual',
            self::FreeShipping => 'Envio gratis',
            self::BuyXGetY => 'Compra X y recibe Y',
        };
    }

    /**
     * Si el descuento se aplica al envio en lugar de al subtotal.
     */
    public function affectsShipping(): bool
    {
        return $this === self::FreeShipping;
    }
}
