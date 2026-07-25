<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Models\Order;

/**
 * Descuenta del inventario lo que lleva un pedido.
 *
 * Debe ejecutarse dentro de la misma transaccion que crea el pedido: si un
 * producto se agoto entre que el cliente lleno el carrito y confirmo, el pedido
 * entero tiene que deshacerse en lugar de quedar guardado sin existencias.
 *
 * @throws InsufficientStock
 */
class DeductStockForOrder
{
    public function __construct(private readonly RecordInventoryMovement $recordMovement) {}

    public function handle(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            // Un producto borrado del catalogo deja el renglon sin referencia, y
            // los que no se llevan por existencias no descuentan nada.
            if ($product === null || ! $product->track_inventory) {
                continue;
            }

            $this->recordMovement->handle(
                product: $product,
                type: InventoryMovementType::Sale,
                quantity: -$item->quantity,
                reason: 'Venta en tienda',
                reference: $order->code,
                order: $order,
            );
        }
    }
}
