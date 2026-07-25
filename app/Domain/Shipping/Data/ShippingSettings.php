<?php

namespace App\Domain\Shipping\Data;

/**
 * Configuracion de envios, tipada.
 *
 * Se construye desde la configuracion administrable. Tenerla como objeto evita
 * que el resto del codigo ande adivinando tipos de un arreglo suelto.
 */
readonly class ShippingSettings
{
    public const GROUP = 'shipping';

    /**
     * @param  array<int, string>  $excludedStates
     * @param  array<int, string>  $excludedPostcodes
     */
    public function __construct(
        public bool $enabled = true,
        public string $methodName = 'Envio nacional',
        public int $flatCents = 9900,
        public bool $freeShippingEnabled = true,
        public int $freeShippingThresholdCents = 80000,
        /**
         * Si el umbral se compara contra el subtotal antes o despues de aplicar
         * descuentos. Cambia cuanto hay que comprar para llegar al envio gratis.
         */
        public bool $thresholdAfterDiscounts = true,
        public array $excludedStates = [],
        public array $excludedPostcodes = [],
        public string $deliveryEstimate = 'Llega en 3 a 5 dias habiles.',
        public int $preparationDays = 1,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'method_name' => $this->methodName,
            'flat_cents' => $this->flatCents,
            'free_shipping_enabled' => $this->freeShippingEnabled,
            'free_shipping_threshold_cents' => $this->freeShippingThresholdCents,
            'threshold_after_discounts' => $this->thresholdAfterDiscounts,
            'excluded_states' => $this->excludedStates,
            'excluded_postcodes' => $this->excludedPostcodes,
            'delivery_estimate' => $this->deliveryEstimate,
            'preparation_days' => $this->preparationDays,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $defaults = new self;

        return new self(
            enabled: (bool) ($values['enabled'] ?? $defaults->enabled),
            methodName: (string) ($values['method_name'] ?? $defaults->methodName),
            flatCents: (int) ($values['flat_cents'] ?? $defaults->flatCents),
            freeShippingEnabled: (bool) ($values['free_shipping_enabled'] ?? $defaults->freeShippingEnabled),
            freeShippingThresholdCents: (int) ($values['free_shipping_threshold_cents'] ?? $defaults->freeShippingThresholdCents),
            thresholdAfterDiscounts: (bool) ($values['threshold_after_discounts'] ?? $defaults->thresholdAfterDiscounts),
            excludedStates: self::normalizeList($values['excluded_states'] ?? []),
            excludedPostcodes: self::normalizeList($values['excluded_postcodes'] ?? []),
            deliveryEstimate: (string) ($values['delivery_estimate'] ?? $defaults->deliveryEstimate),
            preparationDays: (int) ($values['preparation_days'] ?? $defaults->preparationDays),
        );
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private static function normalizeList($value): array
    {
        if (is_string($value)) {
            // El panel permite capturarlos separados por coma o por salto de
            // linea, que es lo natural al pegar una lista.
            $value = preg_split('/[\r\n,;]+/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }
}
