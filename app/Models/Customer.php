<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'email',
        'normalized_email',
        'phone',
        'last_order_at',
        'orders_count',
        'lifetime_value_cents',
    ];

    protected function casts(): array
    {
        return [
            'last_order_at' => 'datetime',
            'orders_count' => 'integer',
            'lifetime_value_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('created_at');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->latest('is_default')->latest('updated_at');
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => filled($value) ? mb_strtolower(trim($value)) : null,
        );
    }
}
