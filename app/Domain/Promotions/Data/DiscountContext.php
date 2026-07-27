<?php

namespace App\Domain\Promotions\Data;

/**
 * Todo lo que el motor necesita saber para decidir descuentos.
 *
 * Se arma en el servidor a partir del catalogo y del pedido, nunca a partir de lo
 * que envie el navegador.
 */
readonly class DiscountContext
{
    /**
     * @param  array<int, CartLine>  $lines
     */
    public function __construct(
        public array $lines,
        public ?string $couponCode = null,
        public ?string $email = null,
        public ?string $paymentMethod = null,
        /**
         * Si es la primera compra de esta persona. Se resuelve fuera del motor,
         * consultando pedidos anteriores por correo.
         */
        public bool $isFirstPurchase = false,
        public bool $isGuest = true,
    ) {}

    public function subtotalCents(): int
    {
        return array_sum(array_map(
            static fn (CartLine $line): int => $line->lineTotalCents(),
            $this->lines,
        ));
    }

    public function totalQuantity(): int
    {
        return array_sum(array_map(
            static fn (CartLine $line): int => $line->quantity,
            $this->lines,
        ));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }
}
