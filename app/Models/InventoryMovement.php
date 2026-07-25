<?php

namespace App\Models;

use App\Domain\Inventory\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Asiento del historial de inventario.
 *
 * El historial es inmutable: una vez escrito, un movimiento no se edita ni se
 * borra. Para corregir un error se registra otro movimiento en sentido
 * contrario, igual que en una contabilidad.
 */
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'type',
        'quantity',
        'stock_after',
        'reason',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity' => 'integer',
            'stock_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // La inmutabilidad se defiende en el modelo y no solo por convencion,
        // para que un descuido en el panel o en una tarea no pueda reescribir
        // el historial.
        static::updating(function (self $movement): void {
            throw new RuntimeException('El historial de inventario es inmutable: no se puede modificar un movimiento.');
        });

        static::deleting(function (self $movement): void {
            throw new RuntimeException('El historial de inventario es inmutable: no se puede eliminar un movimiento.');
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
