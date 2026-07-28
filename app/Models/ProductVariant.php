<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price_cents',
        'compare_at_price_cents',
        'image_path',
        'stock',
        'track_inventory',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'compare_at_price_cents' => 'integer',
            'stock' => 'integer',
            'track_inventory' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function price(): Attribute
    {
        return Attribute::get(fn (): string => '$'.number_format($this->price_cents / 100, 2));
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

            return Storage::disk('public')->url($this->image_path);
        });
    }
}
