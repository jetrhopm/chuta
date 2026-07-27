<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Registro de que una promocion se consumio en un pedido.
 *
 * Es lo que permite hacer valer los limites de uso, incluidos los de clientes sin
 * cuenta, contando por correo normalizado.
 */
class CouponUsage extends Model
{
    protected $fillable = [
        'promotion_id',
        'order_id',
        'email',
        'discount_cents',
    ];

    protected function casts(): array
    {
        return [
            'discount_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $usage): void {
            if ($usage->email !== null) {
                // En minusculas para que contar usos no dependa de como escribio
                // su correo la persona.
                $usage->email = mb_strtolower(trim($usage->email));
            }
        });

        // Borrar un uso permitiria volver a consumir una promocion agotada.
        static::deleting(function (self $usage): void {
            throw new RuntimeException('Los usos de promociones no se eliminan: son el registro que sostiene los limites de uso.');
        });
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
