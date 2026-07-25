<?php

namespace App\Domain\Shipping;

use App\Domain\Settings\SettingsRepository;
use App\Domain\Shipping\Data\ShippingSettings;

class ShippingSettingsRepository
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function get(): ShippingSettings
    {
        return ShippingSettings::fromArray($this->settings->all(ShippingSettings::GROUP));
    }

    public function save(ShippingSettings $settings): void
    {
        $this->settings->setMany(ShippingSettings::GROUP, $settings->toArray());
    }

    /**
     * Siembra los valores iniciales sin pisar lo que ya se haya ajustado.
     */
    public function seedDefaults(): void
    {
        $this->settings->seedMissing(ShippingSettings::GROUP, (new ShippingSettings)->toArray());
    }
}
