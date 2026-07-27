<?php

namespace Database\Factories;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Promocion '.Str::upper(Str::random(4)),
            'description' => null,
            'code' => null,
            'requires_code' => false,
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 10,
            'min_subtotal_cents' => 0,
            'min_quantity' => 0,
            'is_active' => true,
            'priority' => 100,
            'is_exclusive' => false,
            'allow_guests' => true,
            'first_purchase_only' => false,
        ];
    }

    public function coupon(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'requires_code' => true,
            'code' => $code,
        ]);
    }

    public function percentage(int $percent): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::Percentage,
            'discount_value' => $percent,
        ]);
    }

    public function fixedAmount(int $cents): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::FixedAmount,
            'discount_value' => $cents,
        ]);
    }

    public function freeShipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::FreeShipping,
            'discount_value' => 0,
        ]);
    }

    /**
     * Compra X y recibe Y. Para un 3x2: buyXGetY(3, 1).
     */
    public function buyXGetY(int $buy, int $get = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_type' => DiscountType::BuyXGetY,
            'buy_quantity' => $buy,
            'get_quantity' => $get,
        ]);
    }

    public function exclusive(): static
    {
        return $this->state(fn (array $attributes) => ['is_exclusive' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
