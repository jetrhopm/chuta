<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'image_path',
        'price_cents',
        'compare_at_price_cents',
        'stock',
        'stock_minimum',
        'track_inventory',
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'stock' => 'integer',
            'stock_minimum' => 'integer',
            'track_inventory' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class)->latest('created_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Productos que alcanzaron su umbral de aviso.
     *
     * Se excluyen los que no se llevan por existencias y los que tienen el
     * umbral en cero, porque en esos casos el aviso no significa nada.
     */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_inventory', true)
            ->where('stock_minimum', '>', 0)
            ->whereColumn('stock', '<=', 'stock_minimum');
    }

    /**
     * Piezas que se pueden vender ahora mismo.
     *
     * Un producto que no se lleva por existencias no tiene tope.
     */
    public function availableQuantity(): ?int
    {
        return $this->track_inventory ? max(0, $this->stock) : null;
    }

    public function canFulfill(int $quantity): bool
    {
        $available = $this->availableQuantity();

        return $available === null || $available >= $quantity;
    }

    public function hasLowStock(): bool
    {
        return $this->track_inventory
            && $this->stock_minimum > 0
            && $this->stock <= $this->stock_minimum;
    }

    protected function price(): Attribute
    {
        return Attribute::get(fn (): string => '$'.number_format($this->price_cents / 100, 2));
    }

    protected function compareAtPrice(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->compare_at_price_cents === null
                ? null
                : '$'.number_format($this->compare_at_price_cents / 100, 2),
        );
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->image_path === null) {
                return null;
            }

            if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
                return $this->image_path;
            }

            return asset($this->image_path);
        });
    }

    protected function isInStock(): Attribute
    {
        // Un producto que no se lleva por existencias siempre esta disponible.
        return Attribute::get(fn (): bool => ! $this->track_inventory || $this->stock > 0);
    }
}
