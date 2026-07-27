<?php

namespace App\Domain\Promotions\Data;

/**
 * Resultado del calculo de descuentos.
 *
 * Separa lo que rebaja el subtotal de lo que libera el envio, porque el umbral de
 * envio gratis se compara contra el subtotal ya descontado y mezclarlos daria una
 * cifra equivocada.
 */
readonly class DiscountResult
{
    /**
     * @param  array<int, AppliedDiscount>  $discounts
     * @param  array<int, string>  $rejections  Motivos, para explicarle al cliente por que su cupon no aplico.
     */
    public function __construct(
        public array $discounts = [],
        public array $rejections = [],
    ) {}

    public static function empty(): self
    {
        return new self;
    }

    /**
     * Descuento que rebaja el subtotal. El envio gratis no cuenta aqui.
     */
    public function subtotalDiscountCents(): int
    {
        return array_sum(array_map(
            static fn (AppliedDiscount $discount): int => $discount->appliesToShipping ? 0 : $discount->amountCents,
            $this->discounts,
        ));
    }

    public function grantsFreeShipping(): bool
    {
        foreach ($this->discounts as $discount) {
            if ($discount->appliesToShipping) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return $this->discounts === [];
    }

    /**
     * Fotografia para guardar con el pedido.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toBreakdown(): array
    {
        return array_map(
            static fn (AppliedDiscount $discount): array => $discount->toArray(),
            $this->discounts,
        );
    }

    /**
     * Primer motivo de rechazo, que es el que se le muestra al cliente.
     */
    public function firstRejection(): ?string
    {
        return $this->rejections[0] ?? null;
    }
}
