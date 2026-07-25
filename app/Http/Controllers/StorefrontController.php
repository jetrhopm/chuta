<?php

namespace App\Http\Controllers;

use App\Domain\Shipping\ShippingSettingsRepository;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;

class StorefrontController extends Controller
{
    public function __construct(private readonly ShippingSettingsRepository $shippingSettings) {}

    public function __invoke(): View
    {
        $featuredCategories = Category::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->get();

        $featuredProducts = Product::query()
            ->with(['brand', 'category'])
            ->active()
            ->where('is_featured', true)
            ->latest()
            ->get();

        $products = Product::query()
            ->with(['brand', 'category'])
            ->active()
            ->where('is_featured', false)
            ->orderBy('name')
            ->paginate(48);

        return view('storefront.home', [
            'featuredCategories' => $featuredCategories,
            'featuredProducts' => $featuredProducts,
            'products' => $products,
            // La tienda muestra estos valores como adelanto del total. El costo
            // que se cobra lo recalcula el servidor al confirmar el pedido.
            'shipping' => $this->shippingSettings->get(),
        ]);
    }
}
