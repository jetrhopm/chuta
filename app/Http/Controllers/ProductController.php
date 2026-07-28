<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductController extends Controller
{
    public function show(string $slug): View
    {
        $product = Product::query()
            ->with(['brand', 'category', 'images', 'tags', 'variants' => fn ($query) => $query->where('is_active', true)])
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        // Relacionados de la misma categoria. Se excluye el propio producto y se
        // prefieren los que estan disponibles: recomendar algo agotado no ayuda a
        // vender.
        $related = Product::query()
            ->with(['brand', 'category', 'images'])
            ->active()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->getKey())
            ->orderByRaw('CASE WHEN track_inventory = 0 OR stock > 0 THEN 0 ELSE 1 END')
            ->orderByDesc('is_featured')
            ->limit(4)
            ->get();

        return view('storefront.products.show', [
            'product' => $product,
            'related' => $related,
        ]);
    }
}
