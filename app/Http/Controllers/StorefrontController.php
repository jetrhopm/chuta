<?php

namespace App\Http\Controllers;

use App\Domain\Shipping\ShippingSettingsRepository;
use App\Domain\Storefront\StorefrontContentRepository;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class StorefrontController extends Controller
{
    public function __construct(
        private readonly ShippingSettingsRepository $shippingSettings,
        private readonly StorefrontContentRepository $content,
    ) {}

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
            'categoryShortcuts' => $this->categoryShortcuts(),
            'banners' => collect($this->content->displayBanners()),
            'theme' => $this->content->theme(),
            'contentBlocks' => collect($this->content->contentBlocks()),
            'blogPosts' => collect($this->content->blogPosts()),
            'howToBuy' => collect(config('storefront.how_to_buy', [])),
            // La tienda muestra estos valores como adelanto del total. El costo
            // que se cobra lo recalcula el servidor al confirmar el pedido.
            'shipping' => $this->shippingSettings->get(),
        ]);
    }

    /**
     * Accesos rapidos a categorias, resueltos contra las categorias reales.
     *
     * Se conserva el orden configurado y se descartan las que no existen, para
     * que la portada no ofrezca enlaces rotos.
     *
     * @return Collection<int, Category>
     */
    private function categoryShortcuts(): Collection
    {
        $slugs = collect(config('storefront.category_shortcuts', []));

        if ($slugs->isEmpty()) {
            return collect();
        }

        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('slug', $slugs)
            ->get()
            ->keyBy('slug');

        return $slugs
            ->map(fn (string $slug): ?Category => $categories->get($slug))
            ->filter()
            ->values();
    }
}
