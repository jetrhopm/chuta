<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CatalogSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private array $sampleSkus = [
        'CH-WHEY-VAI-5LB',
        'MF-PRE-BLU-30',
        'NC-CREA-300',
        'VS-MULTI-90',
        'CH-MASS-CHO-6LB',
        'MF-PUMP-SAN-25',
    ];

    /**
     * @var list<string>
     */
    private array $sampleCategorySlugs = [
        'proteinas',
        'pre-entrenos',
        'creatinas',
        'vitaminas',
    ];

    /**
     * @var list<string>
     */
    private array $featuredCategorySlugs = [
        'proteina',
        'proteina-isolatada',
        'creatina',
        'oxido-nitrico',
        'aminoacidos',
        'quemadores-y-termogenicos',
        'vitaminas-y-minerales',
        'shakers-y-accesorios',
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/chutamax-products.json');

        /** @var list<array<string, mixed>> $products */
        $products = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);

        Product::query()
            ->whereIn('sku', $this->sampleSkus)
            ->update(['is_active' => false]);

        Category::query()
            ->whereIn('slug', $this->sampleCategorySlugs)
            ->update(['is_active' => false, 'is_featured' => false]);

        $categories = collect($products)
            ->map(fn (array $product): array => [
                'name' => (string) $product['category_name'],
                'slug' => (string) $product['category_slug'],
            ])
            ->unique('slug')
            ->values();

        foreach ($categories as $index => $category) {
            $featuredPosition = array_search($category['slug'], $this->featuredCategorySlugs, true);

            Category::updateOrCreate(
                ['slug' => $category['slug']],
                [
                    'name' => $category['name'],
                    'description' => $this->categoryDescription($category['name']),
                    'is_featured' => $featuredPosition !== false,
                    'is_active' => true,
                    'sort_order' => $featuredPosition === false ? (($index + 20) * 10) : (($featuredPosition + 1) * 10),
                ],
            );
        }

        foreach ($products as $product) {
            $category = Category::where('slug', $product['category_slug'])->firstOrFail();
            $regularPrice = (int) $product['regular_price_cents'];
            $price = (int) $product['price_cents'];

            $existing = Product::where('sku', (string) $product['sku'])->first();

            $attributes = [
                'brand_id' => $existing?->brand_id,
                'category_id' => $category->id,
                'name' => (string) $product['name'],
                'slug' => (string) $product['slug'],
                'short_description' => $this->shortDescription($product),
                'description' => 'Producto del catalogo de la tienda.',
                'price_cents' => $price,
                'compare_at_price_cents' => $regularPrice > $price ? $regularPrice : null,
                'is_featured' => (bool) $product['is_featured'],
                'is_active' => true,
            ];

            // La imagen solo se asigna si el producto todavia no tiene una
            // descargada. Sin esta comprobacion, volver a sembrar devolveria la
            // ruta al sitio de origen y desharia el trabajo de `media:localize`.
            if ($existing === null || $this->isRemote($existing->image_path)) {
                $attributes['image_path'] = $product['image_url'] ?: null;
            }

            // Las existencias tampoco se pisan: las mueve el historial de
            // inventario, y reescribirlas aqui contradiria sus movimientos.
            if ($existing === null) {
                $attributes['stock'] = $product['is_in_stock'] ? 10 : 0;
            }

            Product::updateOrCreate(['sku' => (string) $product['sku']], $attributes);
        }
    }

    private function isRemote(?string $path): bool
    {
        return $path === null || str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function categoryDescription(string $category): string
    {
        return match (mb_strtolower($category)) {
            'proteína', 'proteina', 'proteína isolatada', 'proteína zero carb', 'proteína hidrolizada', 'proteína de carne' => 'Proteínas y fórmulas para recuperación, fuerza y masa muscular.',
            'creatina' => 'Creatinas y fórmulas de rendimiento para entrenamiento diario.',
            'pre workouts', 'óxido nítrico' => 'Energía, enfoque y bombeo para entrenamientos intensos.',
            'vitaminas y minerales' => 'Soporte diario para bienestar, energía e inmunidad.',
            'quemadores y termogénicos' => 'Apoyo para definición, control de peso y energía.',
            default => 'Productos seleccionados del catálogo Chutamax.',
        };
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function shortDescription(array $product): string
    {
        $description = trim((string) $product['short_description']);

        if ($description === '' || str_starts_with($description, '⚠️ ADVERTENCIAS')) {
            return 'Producto de la categoría '.$product['category_name'].' migrado del catálogo Chutamax.';
        }

        return $description;
    }
}
