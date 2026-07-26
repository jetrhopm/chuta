<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Comprobante de una transferencia bancaria.
 *
 * Lo sube el cliente y lo revisa una persona del panel. El archivo vive en un
 * disco privado: un comprobante lleva datos bancarios y no debe quedar accesible
 * con solo adivinar su direccion.
 */
class PaymentReceipt extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'order_id',
        'reviewed_by',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'status',
        'reviewed_at',
        'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Direccion temporal para verlo desde el panel.
     *
     * El disco es privado, asi que no hay una URL permanente: se firma una que
     * caduca.
     */
    public function temporaryUrl(int $minutes = 5): ?string
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->path)) {
            return null;
        }

        return route('receipts.show', ['receipt' => $this->getKey()]).'?'.http_build_query([
            'expires' => now()->addMinutes($minutes)->timestamp,
        ]);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACCEPTED => 'Aceptado',
            self::STATUS_REJECTED => 'Rechazado',
            default => 'Pendiente de revision',
        };
    }
}
