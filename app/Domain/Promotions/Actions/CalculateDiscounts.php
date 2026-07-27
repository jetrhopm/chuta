<?php

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Data\AppliedDiscount;
use App\Domain\Promotions\Data\CartLine;
use App\Domain\Promotions\Data\DiscountContext;
use App\Domain\Promotions\Data\DiscountResult;
use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Promotion;

/**
 * Calcula los descuentos que aplican a un carrito.
 *
 * Es la unica fuente de verdad del descuento. La tienda puede mostrar un adelanto,
 * pero lo que se cobra sale de aqui, en el servidor.
 *
 * Orden de trabajo:
 *
 * 1. Se reunen las promociones automaticas vigentes y, si viene un codigo, su
 *    cupon.
 * 2. Se ordenan por prioridad, y a igual prioridad por identificador, para que el
 *    resultado sea el mismo siempre. Un descuento que cambia entre dos calculos
 *    del mismo carrito es imposible de explicar a un cliente.
 * 3. Se descartan las que no cumplen sus condiciones.
 * 4. Una promocion exclusiva que aplique deja fuera a todas las demas.
 */
class CalculateDiscounts
{
    public function handle(DiscountContext $context): DiscountResult
    {
        if ($context->isEmpty()) {
            return DiscountResult::empty();
        }

        $rechazos = [];
        $candidatas = $this->candidates($context, $rechazos);

        $aplicados = [];
        $subtotal = $context->subtotalCents();

        foreach ($candidatas as $promotion) {
            $monto = $this->amountFor($promotion, $context);

            // Una promocion que no genera beneficio no se anuncia como aplicada:
            // ver un descuento de cero confunde mas que no verlo.
            if ($monto < 1 && ! $promotion->discount_type->affectsShipping()) {
                continue;
            }

            $aplicados[] = new AppliedDiscount(
                promotionId: $promotion->id,
                name: $promotion->name,
                description: $promotion->description,
                code: $promotion->code,
                type: $promotion->discount_type,
                amountCents: $monto,
                appliesToShipping: $promotion->discount_type->affectsShipping(),
            );

            // Exclusiva: si entra, se aplica sola.
            if ($promotion->is_exclusive) {
                return new DiscountResult([end($aplicados)], $rechazos);
            }
        }

        return new DiscountResult(
            $this->capToSubtotal($aplicados, $subtotal),
            $rechazos,
        );
    }

    /**
     * Promociones que pasan todas sus condiciones, en orden de aplicacion.
     *
     * @param  array<int, string>  $rechazos
     * @return array<int, Promotion>
     */
    private function candidates(DiscountContext $context, array &$rechazos): array
    {
        $automaticas = Promotion::query()
            ->currentlyValid()
            ->automatic()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $candidatas = $automaticas
            ->filter(fn (Promotion $p): bool => $this->isEligible($p, $context))
            ->values()
            ->all();

        $cupon = $this->resolveCoupon($context, $rechazos);

        if ($cupon !== null) {
            $candidatas[] = $cupon;

            // Se reordena porque el cupon tiene su propia prioridad y puede tener
            // que aplicarse antes que una promocion automatica.
            usort($candidatas, function (Promotion $a, Promotion $b): int {
                return [$a->priority, $a->id] <=> [$b->priority, $b->id];
            });
        }

        return $candidatas;
    }

    /**
     * Busca el cupon del codigo capturado y explica por que no aplica.
     *
     * @param  array<int, string>  $rechazos
     */
    private function resolveCoupon(DiscountContext $context, array &$rechazos): ?Promotion
    {
        if ($context->couponCode === null || trim($context->couponCode) === '') {
            return null;
        }

        $promotion = Promotion::query()
            ->where('requires_code', true)
            ->where('code', Promotion::normalizeCode($context->couponCode))
            ->first();

        if ($promotion === null) {
            $rechazos[] = 'Ese codigo no existe. Revisalo e intenta de nuevo.';

            return null;
        }

        // Cada motivo se explica por separado para que el cliente sepa que hacer,
        // en lugar de un "no aplica" que no dice nada.
        if (! $promotion->is_active) {
            $rechazos[] = 'Ese cupon ya no esta disponible.';

            return null;
        }

        if (! $promotion->hasStarted()) {
            $rechazos[] = 'Ese cupon todavia no empieza.';

            return null;
        }

        if ($promotion->hasEnded()) {
            $rechazos[] = 'Ese cupon ya vencio.';

            return null;
        }

        if ($promotion->hasReachedGlobalLimit()) {
            $rechazos[] = 'Ese cupon alcanzo su limite de usos.';

            return null;
        }

        if ($promotion->hasReachedCustomerLimit($context->email)) {
            $rechazos[] = 'Ya usaste ese cupon el maximo de veces permitido.';

            return null;
        }

        if ($promotion->first_purchase_only && ! $context->isFirstPurchase) {
            $rechazos[] = 'Ese cupon es solo para la primera compra.';

            return null;
        }

        if (! $promotion->allow_guests && $context->isGuest) {
            $rechazos[] = 'Necesitas iniciar sesion para usar ese cupon.';

            return null;
        }

        if ($context->subtotalCents() < $promotion->min_subtotal_cents) {
            $faltan = $promotion->min_subtotal_cents - $context->subtotalCents();
            $rechazos[] = sprintf('Te faltan $%s para poder usar ese cupon.', number_format($faltan / 100, 2));

            return null;
        }

        if ($context->totalQuantity() < $promotion->min_quantity) {
            $rechazos[] = sprintf('Ese cupon pide al menos %d productos.', $promotion->min_quantity);

            return null;
        }

        if (! $promotion->allowsPaymentMethod($context->paymentMethod)) {
            $rechazos[] = 'Ese cupon no aplica con el metodo de pago elegido.';

            return null;
        }

        if ($this->eligibleLines($promotion, $context) === []) {
            $rechazos[] = 'Ese cupon no aplica a los productos de tu carrito.';

            return null;
        }

        return $promotion;
    }

    /**
     * Condiciones de una promocion automatica.
     *
     * Aqui no se acumulan motivos: una promocion automatica que no aplica
     * simplemente no se muestra, y explicarlo solo generaria ruido.
     */
    private function isEligible(Promotion $promotion, DiscountContext $context): bool
    {
        if ($promotion->hasReachedGlobalLimit()) {
            return false;
        }

        if ($promotion->hasReachedCustomerLimit($context->email)) {
            return false;
        }

        if ($promotion->first_purchase_only && ! $context->isFirstPurchase) {
            return false;
        }

        if (! $promotion->allow_guests && $context->isGuest) {
            return false;
        }

        if ($context->subtotalCents() < $promotion->min_subtotal_cents) {
            return false;
        }

        if ($context->totalQuantity() < $promotion->min_quantity) {
            return false;
        }

        if (! $promotion->allowsPaymentMethod($context->paymentMethod)) {
            return false;
        }

        return $this->eligibleLines($promotion, $context) !== [];
    }

    /**
     * Renglones del carrito a los que alcanza la promocion.
     *
     * @return array<int, CartLine>
     */
    private function eligibleLines(Promotion $promotion, DiscountContext $context): array
    {
        return array_values(array_filter(
            $context->lines,
            fn (CartLine $line): bool => $promotion->coversProduct($line->product),
        ));
    }

    private function amountFor(Promotion $promotion, DiscountContext $context): int
    {
        $elegibles = $this->eligibleLines($promotion, $context);

        $base = array_sum(array_map(
            static fn (CartLine $line): int => $line->lineTotalCents(),
            $elegibles,
        ));

        $monto = match ($promotion->discount_type) {
            // El envio gratis no rebaja el subtotal: lo resuelve el calculo de
            // envio, y aqui solo se marca.
            DiscountType::FreeShipping => 0,

            DiscountType::FixedAmount => min($promotion->discount_value, $base),

            // Division entera: el descuento se redondea hacia abajo al centavo, de
            // modo que nunca se regala una fraccion que no existe.
            DiscountType::Percentage => intdiv($base * $promotion->discount_value, 100),

            DiscountType::BuyXGetY => $this->buyXGetYAmount($promotion, $elegibles),
        };

        if ($promotion->max_benefit_cents !== null) {
            $monto = min($monto, $promotion->max_benefit_cents);
        }

        return max(0, $monto);
    }

    /**
     * Descuento de las promociones que regalan piezas.
     *
     * Por cada grupo completo de `buy_quantity` piezas elegibles se regalan
     * `get_quantity`, y las que se regalan son las **mas baratas**. Es el
     * comportamiento que espera un cliente de un 3x2: paga las dos caras y se
     * lleva la barata.
     *
     * @param  array<int, CartLine>  $lines
     */
    private function buyXGetYAmount(Promotion $promotion, array $lines): int
    {
        $porGrupo = max(1, (int) $promotion->buy_quantity);
        $gratisPorGrupo = max(1, (int) $promotion->get_quantity);

        // No tiene sentido regalar tantas piezas como pide el grupo: seria
        // regalarlo todo.
        if ($gratisPorGrupo >= $porGrupo) {
            $gratisPorGrupo = $porGrupo - 1;
        }

        if ($gratisPorGrupo < 1) {
            return 0;
        }

        // Se desarma en piezas individuales para poder ordenar por precio.
        $precios = [];

        foreach ($lines as $line) {
            foreach ($line->unitPrices() as $precio) {
                $precios[] = $precio;
            }
        }

        $grupos = intdiv(count($precios), $porGrupo);

        if ($grupos < 1) {
            return 0;
        }

        sort($precios);

        // Las mas baratas primero: son las que se regalan.
        return array_sum(array_slice($precios, 0, $grupos * $gratisPorGrupo));
    }

    /**
     * Evita que la suma de descuentos supere el subtotal.
     *
     * Sin este tope, dos promociones acumulables podrian dejar un total negativo y
     * convertir una venta en una devolucion.
     *
     * @param  array<int, AppliedDiscount>  $aplicados
     * @return array<int, AppliedDiscount>
     */
    private function capToSubtotal(array $aplicados, int $subtotal): array
    {
        $acumulado = 0;
        $resultado = [];

        foreach ($aplicados as $discount) {
            if ($discount->appliesToShipping) {
                $resultado[] = $discount;

                continue;
            }

            $disponible = max(0, $subtotal - $acumulado);

            if ($disponible < 1) {
                continue;
            }

            $monto = min($discount->amountCents, $disponible);
            $acumulado += $monto;

            $resultado[] = $monto === $discount->amountCents
                ? $discount
                : new AppliedDiscount(
                    promotionId: $discount->promotionId,
                    name: $discount->name,
                    description: $discount->description,
                    code: $discount->code,
                    type: $discount->type,
                    amountCents: $monto,
                    appliesToShipping: false,
                );
        }

        return $resultado;
    }
}
