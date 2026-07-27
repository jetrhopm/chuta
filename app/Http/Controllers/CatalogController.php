<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\Actions\SearchProducts;
use App\Domain\Catalog\Data\CatalogFilters;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function __construct(private readonly SearchProducts $search) {}

    public function __invoke(Request $request): View
    {
        $filters = CatalogFilters::fromQuery($request->query());

        return view('storefront.catalog.index', [
            'filters' => $filters,
            'products' => $this->search->handle($filters),
            // Solo las que tienen productos: ofrecer un filtro que no devuelve
            // nada es una via muerta.
            'categories' => Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name']),
            'brands' => Brand::query()
                ->where('is_active', true)
                ->whereHas('products', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }
}
