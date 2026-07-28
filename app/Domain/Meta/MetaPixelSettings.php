<?php

namespace App\Domain\Meta;

readonly class MetaPixelSettings
{
    public const GROUP = 'meta_ads';

    public function __construct(
        public bool $enabled,
        public ?string $pixelId,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        $pixelId = trim((string) ($values['pixel_id'] ?? ''));

        return new self(
            enabled: (bool) ($values['enabled'] ?? false),
            pixelId: $pixelId === '' ? null : $pixelId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'pixel_id' => $this->pixelId,
        ];
    }

    public function canTrack(): bool
    {
        return $this->enabled && $this->pixelId !== null;
    }
}
