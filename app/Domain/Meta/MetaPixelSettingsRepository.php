<?php

namespace App\Domain\Meta;

use App\Domain\Settings\SettingsRepository;

class MetaPixelSettingsRepository
{
    public function __construct(private readonly SettingsRepository $settings) {}

    public function get(): MetaPixelSettings
    {
        return MetaPixelSettings::fromArray($this->settings->all(MetaPixelSettings::GROUP));
    }

    public function save(MetaPixelSettings $settings): void
    {
        $this->settings->setMany(MetaPixelSettings::GROUP, $settings->toArray());
    }
}
