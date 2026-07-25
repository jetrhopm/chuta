<?php

namespace App\Domain\Inventory\Exceptions;

use App\Models\Product;
use RuntimeException;

/**
 * Se lanza cuando un movimiento dejaria las existencias en negativo.
 *
 * Lleva los datos crudos para que quien la atrape decida como contarlo. El
 * mensaje de esta excepcion no se muestra al cliente: la tienda traduce el
 * fallo a un aviso comprensible.
 */
class InsufficientStock extends RuntimeException
{
    public function __construct(
        public readonly Product $product,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(sprintf(
            'Existencias insuficientes para el producto %d (%s): se pidieron %d y hay %d.',
            $product->id,
            $product->sku,
            $requested,
            $available,
        ));
    }

    public function customerMessage(): string
    {
        if ($this->available < 1) {
            return sprintf('%s se agoto mientras completabas tu pedido.', $this->product->name);
        }

        return sprintf(
            'Solo quedan %d piezas de %s. Ajusta la cantidad para continuar.',
            $this->available,
            $this->product->name,
        );
    }
}
