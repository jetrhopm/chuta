<?php

namespace App\Domain\Payments\Data;

readonly class RefundResult
{
    public function __construct(
        public bool $successful,
        public ?string $externalId = null,
        public int $refundedCents = 0,
        public ?string $failureReason = null,
        /**
         * @var array<string, mixed>
         */
        public array $snapshot = [],
    ) {}

    public static function failed(string $reason): self
    {
        return new self(successful: false, failureReason: $reason);
    }
}
