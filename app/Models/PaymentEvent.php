<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso recibido de un proveedor de pago.
 *
 * Se guardan tambien los que llegan con firma invalida: un webhook falso es
 * justo lo que interesa poder revisar despues.
 */
class PaymentEvent extends Model
{
    protected $fillable = [
        'payment_attempt_id',
        'provider',
        'event_id',
        'event_type',
        'external_id',
        'signature_valid',
        'payload',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'signature_valid' => 'boolean',
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class, 'payment_attempt_id');
    }
}
