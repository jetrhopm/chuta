<?php

namespace App\Domain\Promotions\Data;

use App\Domain\Promotions\Enums\DiscountType;

/**
 * Un descuento ya calculado.
 *
 * Lleva el nombre y la descripcion que vera el cliente, ademas del identificador
 * de la promocion. Se convierte a arreglo para guardarse con el pedido como
 * fotografia inmutable: si la promocion cambia despues, el pedido conserva lo que
 * de verdad se aplico.
 */
readonly class AppliedDiscount
{
    public function __construct(
        public int $promotionId,
        public string $name,
        public ?string $description,
        public ?string $code,
        public DiscountType $type,
        public int $amountCents,
        public bool $appliesToShipping = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'promotion_id' => $this->promotionId,
            'name' => $this->name,
            'description' => $this->description,
            'code' => $this->code,
            'type' => $this->type->value,
            'amount_cents' => $this->amountCents,
            'applies_to_shipping' => $this->appliesToShipping,
        ];
    }

    public function formattedAmount(): string
    {
        return '$'.number_format($this->amountCents / 100, 2);
    }
}
