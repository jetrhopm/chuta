<?php

namespace App\Domain\Payments\Data;

use App\Domain\Payments\Enums\PaymentStatus;

/**
 * Estado que reporta el proveedor sobre un pago.
 *
 * Lleva el importe y la moneda porque hay que compararlos contra el pedido antes
 * de dar el pago por bueno: confiar solo en el estado permitiria aprobar un
 * pedido cobrado por menos de lo que vale.
 */
readonly class PaymentStatusResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $externalId = null,
        public ?int $amountCents = null,
        public ?string $currency = null,
        public ?string $failureReason = null,
        /**
         * @var array<string, mixed>
         */
        public array $snapshot = [],
    ) {}

    /**
     * Comprueba que lo cobrado coincide con lo que se debia cobrar.
     *
     * Un proveedor que no informe importe deja esto sin verificar, y en ese caso
     * quien llama decide si acepta el riesgo o consulta de otra forma.
     */
    public function matches(int $expectedCents, string $expectedCurrency): bool
    {
        if ($this->amountCents === null || $this->currency === null) {
            return false;
        }

        return $this->amountCents === $expectedCents
            && strtoupper($this->currency) === strtoupper($expectedCurrency);
    }
}
