<?php

namespace App\Domain\Shipping\Data;

/**
 * Resultado del calculo de envio.
 *
 * Lleva tambien lo que falta para el envio gratis, porque la tienda necesita
 * mostrarlo y ese numero debe salir del mismo calculo que cobra, no de una
 * cuenta aparte en el navegador.
 */
readonly class ShippingQuote
{
    public function __construct(
        public int $costCents,
        public bool $isFree,
        public int $remainingForFreeCents,
        public string $methodName,
        public string $deliveryEstimate,
        /**
         * Motivo por el que no se puede enviar. Nulo cuando si se puede.
         * Es un texto para el cliente, sin tecnicismos.
         */
        public ?string $unavailableReason = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->unavailableReason === null;
    }

    /**
     * Mensaje de progreso hacia el envio gratis.
     */
    public function freeShippingMessage(): ?string
    {
        if ($this->isFree) {
            return 'Ya tienes envio gratis.';
        }

        if ($this->remainingForFreeCents < 1) {
            return null;
        }

        return sprintf(
            'Te faltan $%s para obtener envio gratis.',
            number_format($this->remainingForFreeCents / 100, 2),
        );
    }
}
