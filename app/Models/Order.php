<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'status',
        'payment_method',
        'payment_status',
        'subtotal_cents',
        'shipping_cents',
        'total_cents',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_street',
        'shipping_number',
        'shipping_neighborhood',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal_cents' => 'integer',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Un pedido es un registro contable y no se borra: se cancela, dejando
        // constancia y reponiendo inventario.
        //
        // La invariante vive aqui y no en la Policy porque el superadministrador
        // se salta cualquier Policy por el Gate::before de AuthServiceProvider.
        // Una Policy sola no la garantizaria.
        static::deleting(function (self $order): void {
            throw new RuntimeException('Los pedidos no se eliminan. Cancela el pedido para conservar el historial.');
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn (): string => '$'.number_format($this->total_cents / 100, 2));
    }
}
