<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Shipping\Data\ShippingQuote;
use App\Domain\Shipping\Data\ShippingSettings;
use App\Domain\Shipping\ShippingSettingsRepository;

/**
 * Calcula el costo de envio.
 *
 * Es la unica fuente de verdad del envio. La tienda puede mostrar un calculo
 * provisional para que el cliente vea el total al instante, pero lo que se cobra
 * sale siempre de aqui, en el servidor.
 */
class CalculateShipping
{
    public function __construct(private readonly ShippingSettingsRepository $repository) {}

    /**
     * @param  int  $subtotalCents  Subtotal de los productos, sin envio.
     * @param  int  $discountCents  Descuentos ya aplicados al subtotal.
     */
    public function handle(
        int $subtotalCents,
        int $discountCents = 0,
        ?string $state = null,
        ?string $postcode = null,
    ): ShippingQuote {
        $settings = $this->repository->get();

        if (! $settings->enabled) {
            return $this->unavailable($settings, 'Por ahora no estamos realizando envios.');
        }

        if ($this->isExcludedState($settings, $state)) {
            return $this->unavailable($settings, 'Todavia no llegamos a ese estado. Escribenos para buscar una alternativa.');
        }

        if ($this->isExcludedPostcode($settings, $postcode)) {
            return $this->unavailable($settings, 'No tenemos cobertura en ese codigo postal. Escribenos para buscar una alternativa.');
        }

        // El umbral se compara contra el subtotal antes o despues de descuentos
        // segun la configuracion: cambia cuanto hay que comprar para llegar al
        // envio gratis.
        $eligibleCents = $settings->thresholdAfterDiscounts
            ? max(0, $subtotalCents - $discountCents)
            : $subtotalCents;

        if (! $settings->freeShippingEnabled) {
            return new ShippingQuote(
                costCents: $settings->flatCents,
                isFree: false,
                // Sin envio gratis activo no hay progreso que mostrar.
                remainingForFreeCents: 0,
                methodName: $settings->methodName,
                deliveryEstimate: $settings->deliveryEstimate,
            );
        }

        $isFree = $eligibleCents >= $settings->freeShippingThresholdCents;

        return new ShippingQuote(
            costCents: $isFree ? 0 : $settings->flatCents,
            isFree: $isFree,
            remainingForFreeCents: $isFree
                ? 0
                : $settings->freeShippingThresholdCents - $eligibleCents,
            methodName: $settings->methodName,
            deliveryEstimate: $settings->deliveryEstimate,
        );
    }

    private function unavailable(ShippingSettings $settings, string $reason): ShippingQuote
    {
        return new ShippingQuote(
            costCents: 0,
            isFree: false,
            remainingForFreeCents: 0,
            methodName: $settings->methodName,
            deliveryEstimate: $settings->deliveryEstimate,
            unavailableReason: $reason,
        );
    }

    private function isExcludedState(ShippingSettings $settings, ?string $state): bool
    {
        if ($state === null || $settings->excludedStates === []) {
            return false;
        }

        // Comparacion laxa a proposito: la lista la captura una persona y el
        // estado lo escribe otra, asi que no se puede exigir que coincidan
        // acentos ni mayusculas.
        $needle = $this->normalize($state);

        foreach ($settings->excludedStates as $excluded) {
            if ($this->normalize($excluded) === $needle) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedPostcode(ShippingSettings $settings, ?string $postcode): bool
    {
        if ($postcode === null || $settings->excludedPostcodes === []) {
            return false;
        }

        return in_array(trim($postcode), $settings->excludedPostcodes, strict: true);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        // Quita acentos para que "Michoacan" y "Michoacán" sean lo mismo.
        return strtr($value, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }
}
