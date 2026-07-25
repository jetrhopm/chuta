<?php

namespace App\Domain\Payments\Data;

use App\Models\PaymentAttempt;

readonly class RefundRequestData
{
    public function __construct(
        public PaymentAttempt $attempt,
        /**
         * Importe a devolver. Igual al del pago para un reembolso total.
         */
        public int $amountCents,
        public string $currency,
        public ?string $reason = null,
        /**
         * Clave de idempotencia del reembolso, para que un reintento no devuelva
         * el dinero dos veces.
         */
        public ?string $idempotencyKey = null,
    ) {}

    public function isPartial(): bool
    {
        return $this->amountCents < $this->attempt->amount_cents;
    }

    public function amountAsDecimalString(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }
}
