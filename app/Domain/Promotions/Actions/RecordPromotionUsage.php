<?php

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Data\DiscountResult;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Promotion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Deja constancia de que un pedido consumio unas promociones.
 *
 * Es lo que sostiene los limites de uso. Sin este registro, un cupon de un solo
 * uso se podria canjear indefinidamente.
 */
class RecordPromotionUsage
{
    public function handle(Order $order, DiscountResult $result): void
    {
        if ($result->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($order, $result): void {
            foreach ($result->discounts as $discount) {
                try {
                    CouponUsage::create([
                        'promotion_id' => $discount->promotionId,
                        'order_id' => $order->getKey(),
                        'email' => $order->customer_email,
                        'discount_cents' => $discount->amountCents,
                    ]);
                } catch (QueryException $exception) {
                    // La clave unica de (promocion, pedido) evita contar dos veces
                    // el mismo consumo si el checkout se reintenta.
                    if ($this->isDuplicate($exception)) {
                        continue;
                    }

                    throw $exception;
                }

                // Incremento atomico en la base y no leyendo y escribiendo desde
                // PHP: dos compras simultaneas del ultimo cupon disponible no
                // pueden acabar contando un solo uso.
                Promotion::whereKey($discount->promotionId)->increment('uses_count');
            }
        });
    }

    private function isDuplicate(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
