<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $brands = collect([
            ['name' => 'Chuta Performance', 'slug' => 'chuta-performance'],
            ['name' => 'NutriCore', 'slug' => 'nutricore'],
            ['name' => 'MaxFuel', 'slug' => 'maxfuel'],
            ['name' => 'VitaSport', 'slug' => 'vitasport'],
        ])->mapWithKeys(fn (array $brand): array => [
            $brand['slug'] => Brand::updateOrCreate(['slug' => $brand['slug']], $brand),
        ]);

        $categories = collect([
            [
                'name' => 'Proteinas',
                'slug' => 'proteinas',
                'description' => 'Whey, aislados y mezclas para recuperacion muscular.',
                'is_featured' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Pre entrenos',
                'slug' => 'pre-entrenos',
                'description' => 'Energia, enfoque y bombeo para sesiones exigentes.',
                'is_featured' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Creatinas',
                'slug' => 'creatinas',
                'description' => 'Monohidratada y formulas de fuerza diaria.',
                'is_featured' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Vitaminas',
                'slug' => 'vitaminas',
                'description' => 'Soporte diario para bienestar, energia e inmunidad.',
                'is_featured' => true,
                'sort_order' => 40,
            ],
        ])->mapWithKeys(fn (array $category): array => [
            $category['slug'] => Category::updateOrCreate(['slug' => $category['slug']], $category),
        ]);

        $products = [
            [
                'brand' => 'chuta-performance',
                'category' => 'proteinas',
                'name' => 'Whey Protein Vainilla 5 lb',
                'slug' => 'whey-protein-vainilla-5-lb',
                'sku' => 'CH-WHEY-VAI-5LB',
                'short_description' => 'Proteina de rapida absorcion para recuperacion y masa magra.',
                'price_cents' => 89900,
                'compare_at_price_cents' => 99900,
                'stock' => 18,
                'is_featured' => true,
            ],
            [
                'brand' => 'maxfuel',
                'category' => 'pre-entrenos',
                'name' => 'Pre Workout Blue Rush 30 serv',
                'slug' => 'pre-workout-blue-rush-30-serv',
                'sku' => 'MF-PRE-BLU-30',
                'short_description' => 'Formula intensa con cafeina, citrulina y beta alanina.',
                'price_cents' => 54900,
                'compare_at_price_cents' => 62900,
                'stock' => 12,
                'is_featured' => true,
            ],
            [
                'brand' => 'nutricore',
                'category' => 'creatinas',
                'name' => 'Creatina Monohidratada 300 g',
                'slug' => 'creatina-monohidratada-300-g',
                'sku' => 'NC-CREA-300',
                'short_description' => 'Creatina pura micronizada para fuerza y rendimiento.',
                'price_cents' => 39900,
                'compare_at_price_cents' => null,
                'stock' => 25,
                'is_featured' => true,
            ],
            [
                'brand' => 'vitasport',
                'category' => 'vitaminas',
                'name' => 'Multivitaminico Sport 90 caps',
                'slug' => 'multivitaminico-sport-90-caps',
                'sku' => 'VS-MULTI-90',
                'short_description' => 'Vitaminas y minerales para rutina activa.',
                'price_cents' => 32900,
                'compare_at_price_cents' => 37900,
                'stock' => 30,
                'is_featured' => true,
            ],
            [
                'brand' => 'chuta-performance',
                'category' => 'proteinas',
                'name' => 'Mass Gainer Chocolate 6 lb',
                'slug' => 'mass-gainer-chocolate-6-lb',
                'sku' => 'CH-MASS-CHO-6LB',
                'short_description' => 'Calorias limpias, proteina y carbohidratos para volumen.',
                'price_cents' => 74900,
                'compare_at_price_cents' => 84900,
                'stock' => 9,
                'is_featured' => false,
            ],
            [
                'brand' => 'maxfuel',
                'category' => 'pre-entrenos',
                'name' => 'Pump Formula Sandia 25 serv',
                'slug' => 'pump-formula-sandia-25-serv',
                'sku' => 'MF-PUMP-SAN-25',
                'short_description' => 'Bombeo sin estimulantes para entrenar tarde.',
                'price_cents' => 47900,
                'compare_at_price_cents' => null,
                'stock' => 14,
                'is_featured' => false,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                [
                    ...Arr::except($product, ['brand', 'category']),
                    'brand_id' => $brands[$product['brand']]->id,
                    'category_id' => $categories[$product['category']]->id,
                    'is_active' => true,
                ],
            );
        }
    }
}
