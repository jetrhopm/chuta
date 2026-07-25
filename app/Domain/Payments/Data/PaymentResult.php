<?php

namespace App\Domain\Payments\Data;

use App\Domain\Payments\Enums\PaymentStatus;

/**
 * Resultado de haber pedido un cobro.
 *
 * Nunca lleva secretos: lo que se guarda y lo que llega al navegador sale de
 * aqui, asi que solo viajan el identificador externo y la direccion a la que hay
 * que mandar al cliente.
 */
readonly class PaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $externalId = null,
        /**
         * Direccion a la que redirigir al cliente. Nula cuando el metodo se
         * resuelve dentro de la tienda, como la transferencia.
         */
        public ?string $checkoutUrl = null,
        /**
         * Instrucciones para el cliente cuando no hay redireccion.
         */
        public ?string $instructions = null,
        /**
         * Motivo del fallo, ya redactado para el cliente.
         */
        public ?string $failureReason = null,
        /**
         * Datos del proveedor que conviene conservar, ya sin secretos.
         *
         * @var array<string, mixed>
         */
        public array $snapshot = [],
    ) {}

    public function failed(): bool
    {
        return $this->status === PaymentStatus::Rejected;
    }
}
