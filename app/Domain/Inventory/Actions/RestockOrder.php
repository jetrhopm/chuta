<?php

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Devuelve al inventario lo que llevaba un pedido cancelado o devuelto.
 *
 * La reposicion es controlada: solo repone lo que de verdad se habia descontado
 * y nunca dos veces el mismo pedido. Sin esa comprobacion, cancelar dos veces
 * inflaria las existencias con piezas que no existen.
 */
class RestockOrder
{
    public function __construct(private readonly RecordInventoryMovement $recordMovement) {}

    public function handle(
        Order $order,
        InventoryMovementType $type = InventoryMovementType::Cancellation,
        ?string $reason = null,
        ?User $user = null,
    ): void {
        DB::transaction(function () use ($order, $type, $reason, $user): void {
            foreach ($order->items as $item) {
                $product = $item->product;

                if ($product === null || ! $product->track_inventory) {
                    continue;
                }

                $alreadyDeducted = $this->quantityDeducted($order, (int) $item->product_id);
                $alreadyRestocked = $this->quantityRestocked($order, (int) $item->product_id);
                $pending = $alreadyDeducted - $alreadyRestocked;

                if ($pending < 1) {
                    continue;
                }

                $this->recordMovement->handle(
                    product: $product,
                    type: $type,
                    quantity: $pending,
                    reason: $reason ?? $type->label().' de pedido',
                    reference: $order->code,
                    user: $user,
                    order: $order,
                );
            }
        });
    }

    private function quantityDeducted(Order $order, int $productId): int
    {
        return abs((int) InventoryMovement::query()
            ->where('order_id', $order->getKey())
            ->where('product_id', $productId)
            ->where('type', InventoryMovementType::Sale)
            ->sum('quantity'));
    }

    private function quantityRestocked(Order $order, int $productId): int
    {
        return (int) InventoryMovement::query()
            ->where('order_id', $order->getKey())
            ->where('product_id', $productId)
            ->whereIn('type', [
                InventoryMovementType::Cancellation->value,
                InventoryMovementType::CustomerReturn->value,
            ])
            ->sum('quantity');
    }
}
