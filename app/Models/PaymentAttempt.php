<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'status',
        'amount_cents',
        'currency',
        'external_id',
        'checkout_url',
        'idempotency_key',
        'sandbox',
        'request_snapshot',
        'response_snapshot',
        'failure_reason',
        'refunded_cents',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => PaymentStatus::class,
            'amount_cents' => 'integer',
            'refunded_cents' => 'integer',
            'sandbox' => 'boolean',
            'request_snapshot' => 'array',
            'response_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function refundableCents(): int
    {
        return max(0, $this->amount_cents - $this->refunded_cents);
    }
}
