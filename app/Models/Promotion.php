<?php

namespace App\Models;

use App\Domain\Promotions\Enums\DiscountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'code',
        'requires_code',
        'discount_type',
        'discount_value',
        'buy_quantity',
        'get_quantity',
        'min_subtotal_cents',
        'min_quantity',
        'max_benefit_cents',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'is_exclusive',
        'max_uses',
        'max_uses_per_customer',
        'allow_guests',
        'first_purchase_only',
        'product_ids',
        'category_ids',
        'brand_ids',
        'excluded_product_ids',
        'payment_methods',
    ];

    protected function casts(): array
    {
        return [
            'requires_code' => 'boolean',
            'discount_type' => DiscountType::class,
            'discount_value' => 'integer',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'min_subtotal_cents' => 'integer',
            'min_quantity' => 'integer',
            'max_benefit_cents' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'priority' => 'integer',
            'is_exclusive' => 'boolean',
            'max_uses' => 'integer',
            'max_uses_per_customer' => 'integer',
            'uses_count' => 'integer',
            'allow_guests' => 'boolean',
            'first_purchase_only' => 'boolean',
            'product_ids' => 'array',
            'category_ids' => 'array',
            'brand_ids' => 'array',
            'excluded_product_ids' => 'array',
            'payment_methods' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // El codigo se normaliza para que el cliente pueda escribirlo como
        // quiera: los cupones se comparten de boca en boca y nadie respeta
        // mayusculas ni espacios.
        static::saving(function (self $promotion): void {
            if ($promotion->code !== null) {
                $promotion->code = self::normalizeCode($promotion->code);
            }
        });
    }

    public static function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    /**
     * Promociones automaticas vigentes, en orden de aplicacion.
     */
    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('requires_code', false);
    }

    /**
     * Vigentes ahora: activas y dentro de sus fechas.
     *
     * Las fechas nulas significan "sin limite" por ese lado.
     */
    public function scopeCurrentlyValid(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function hasReachedGlobalLimit(): bool
    {
        return $this->max_uses !== null && $this->uses_count >= $this->max_uses;
    }

    public function timesUsedBy(?string $email): int
    {
        if ($email === null || $email === '') {
            return 0;
        }

        return $this->usages()->where('email', mb_strtolower(trim($email)))->count();
    }

    public function hasReachedCustomerLimit(?string $email): bool
    {
        if ($this->max_uses_per_customer === null) {
            return false;
        }

        // Sin correo no hay forma de contar usos por persona. Se deja pasar en
        // lugar de bloquear: el limite global sigue protegiendo la promocion.
        if ($email === null || $email === '') {
            return false;
        }

        return $this->timesUsedBy($email) >= $this->max_uses_per_customer;
    }

    /**
     * Si la promocion alcanza a un producto concreto.
     *
     * Sin listas de alcance aplica a todo el catalogo. Las exclusiones ganan
     * siempre sobre las inclusiones.
     */
    public function coversProduct(Product $product): bool
    {
        if (in_array($product->id, $this->excluded_product_ids ?? [], strict: false)) {
            return false;
        }

        $tieneAlcance = ($this->product_ids ?? []) !== []
            || ($this->category_ids ?? []) !== []
            || ($this->brand_ids ?? []) !== [];

        if (! $tieneAlcance) {
            return true;
        }

        return in_array($product->id, $this->product_ids ?? [], strict: false)
            || in_array($product->category_id, $this->category_ids ?? [], strict: false)
            || ($product->brand_id !== null && in_array($product->brand_id, $this->brand_ids ?? [], strict: false));
    }

    public function allowsPaymentMethod(?string $method): bool
    {
        $permitidos = $this->payment_methods ?? [];

        if ($permitidos === [] || $method === null) {
            return true;
        }

        return in_array($method, $permitidos, strict: true);
    }
}
