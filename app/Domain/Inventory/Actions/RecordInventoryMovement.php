<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Unico punto por el que se modifican las existencias.
 *
 * Toda entrada o salida pasa por aqui para que el historial y la columna de
 * existencias nunca puedan contradecirse. Nadie deberia escribir `stock` a mano.
 */
class RecordInventoryMovement
{
    /**
     * @param  int  $quantity  Con signo: positivo entra, negativo sale.
     *
     * @throws InsufficientStock
     */
    public function handle(
        Product $product,
        InventoryMovementType $type,
        int $quantity,
        ?string $reason = null,
        ?string $reference = null,
        ?User $user = null,
        ?Order $order = null,
    ): InventoryMovement {
        if ($quantity === 0) {
            throw new \InvalidArgumentException('Un movimiento de inventario no puede ser de cero piezas.');
        }

        return DB::transaction(function () use ($product, $type, $quantity, $reason, $reference, $user, $order): InventoryMovement {
            // Se relee el producto con la fila bloqueada. Es lo que evita que dos
            // compras simultaneas lean las mismas existencias y vendan dos veces
            // la ultima pieza: la segunda espera a que la primera termine.
            $locked = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $stockAfter = $locked->stock + $quantity;

            if ($stockAfter < 0) {
                throw new InsufficientStock($locked, abs($quantity), $locked->stock);
            }

            $locked->forceFill(['stock' => $stockAfter])->save();

            $movement = InventoryMovement::create([
                'product_id' => $locked->getKey(),
                'user_id' => $user?->getKey(),
                'order_id' => $order?->getKey(),
                'type' => $type,
                'quantity' => $quantity,
                'stock_after' => $stockAfter,
                'reason' => $reason,
                'reference' => $reference,
            ]);

            // Se refresca la instancia que recibio quien llamo, para que no siga
            // trabajando con unas existencias que ya cambiaron.
            $product->setAttribute('stock', $stockAfter);
            $product->syncOriginalAttribute('stock');

            return $movement;
        });
    }
}
