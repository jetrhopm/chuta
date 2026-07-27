<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
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
        'discount_cents',
        'discount_breakdown',
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
            'discount_cents' => 'integer',
            // Fotografia inmutable de los descuentos aplicados: no se recalcula,
            // asi que modificar la promocion despues no cambia este pedido.
            'discount_breakdown' => 'array',
            'shipping_cents' => 'integer',
            'total_cents' => 'integer',
            // El estado del pago va aparte del estado del pedido: un pedido puede
            // estar en preparacion con el pago aprobado, o entregado con un
            // contracargo posterior.
            'payment_status' => PaymentStatus::class,
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

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class)->latest('id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->latest('id');
    }

    /**
     * Ultimo intento de pago, que es el que la tienda muestra al cliente.
     */
    public function currentPaymentAttempt(): ?PaymentAttempt
    {
        return $this->paymentAttempts()->first();
    }

    /**
     * Direccion firmada para consultar el pedido.
     *
     * Se usa una firma y no el folio a secas para que nadie pueda ver pedidos
     * ajenos probando folios: el enlace solo funciona con la firma que emite este
     * servidor.
     */
    public function trackingUrl(): string
    {
        return URL::signedRoute('orders.show', ['code' => $this->code]);
    }

    protected function total(): Attribute
    {
        return Attribute::get(fn (): string => '$'.number_format($this->total_cents / 100, 2));
    }
}
