<?php

namespace App\Domain\Payments\Data;

use App\Models\Order;

/**
 * Datos con los que se pide un cobro.
 *
 * El importe viaja en centavos, como en todo el proyecto. Cada adaptador lo
 * convierte al formato que espera su proveedor; ninguno recibe un float desde
 * aqui.
 */
readonly class PaymentRequestData
{
    public function __construct(
        public Order $order,
        public int $amountCents,
        public string $currency,
        public string $description,
        /**
         * Clave de idempotencia del intento. Evita que un doble clic o un
         * reintento de la red generen dos cobros por el mismo pedido.
         */
        public string $idempotencyKey,
        public string $returnUrl,
        public string $cancelUrl,
        public string $webhookUrl,
    ) {}

    /**
     * Importe en unidades, para los proveedores que lo piden asi.
     *
     * Se construye con formato de texto y no dividiendo en punto flotante, para
     * que no aparezcan errores de redondeo en el importe que se cobra.
     */
    public function amountAsDecimalString(): string
    {
        return number_format($this->amountCents / 100, 2, '.', '');
    }
}
