<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_featured',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'stock' => 'integer',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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

    protected function isInStock(): Attribute
    {
        return Attribute::get(fn (): bool => $this->stock > 0);
    }
}
