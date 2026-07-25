<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(5),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'price_cents' => fake()->numberBetween(15000, 250000),
            'compare_at_price_cents' => null,
            'stock' => 10,
            // Los valores por omision de la tabla no llegan al modelo recien
            // creado, asi que se declaran aqui para que las comprobaciones sobre
            // la instancia en memoria no los lean como nulos.
            'stock_minimum' => 0,
            'track_inventory' => true,
            'is_featured' => false,
            'is_active' => true,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => ['stock' => 0]);
    }

    public function withStock(int $quantity): static
    {
        return $this->state(fn (array $attributes) => ['stock' => $quantity]);
    }

    public function lowStockThreshold(int $threshold): static
    {
        return $this->state(fn (array $attributes) => ['stock_minimum' => $threshold]);
    }

    /**
     * Producto que no se lleva por existencias: se puede vender sin tope.
     */
    public function untracked(): static
    {
        return $this->state(fn (array $attributes) => [
            'track_inventory' => false,
            'stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => ['is_featured' => true]);
    }
}
