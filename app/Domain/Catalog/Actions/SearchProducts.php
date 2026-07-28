<?php

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\Data\CatalogFilters;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Busqueda y filtrado del catalogo.
 *
 * Usa el indice de texto completo de MySQL, sin depender de ningun servicio
 * externo. Cuando el motor no lo admite —o el termino es demasiado corto para el
 * indice— cae a una coincidencia parcial, de modo que la busqueda nunca deja de
 * funcionar.
 */
class SearchProducts
{
    public function handle(CatalogFilters $filters, int $perPage = 24): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['brand:id,name', 'category:id,name'])
            ->active();

        $this->applyTerm($query, $filters);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters);

        return $query
            ->paginate($perPage)
            // Los filtros viajan en la paginacion: sin esto, pasar a la pagina dos
            // perderia la busqueda.
            ->appends($filters->toQuery());
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyTerm(Builder $query, CatalogFilters $filters): void
    {
        if (! $filters->hasTerm()) {
            return;
        }

        $term = (string) $filters->term;

        if (! $this->supportsFullText()) {
            $this->applyLikeFallback($query, $term);

            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            // El SKU se busca por coincidencia exacta y aparte: el analizador de
            // palabras parte cadenas como "CHUTAMAX-3044" en trozos inutiles.
            $inner->where('sku', 'like', $term.'%')
                ->orWhereFullText(['name', 'short_description', 'description'], $term)
                // Respaldo dentro de la misma consulta: el indice ignora palabras
                // de menos de cuatro letras y terminos parciales, y sin esto
                // buscar "whey" o "prote" no devolveria nada.
                ->orWhere('name', 'like', '%'.$term.'%')
                ->orWhereHas('tags', fn (Builder $tags): Builder => $tags->where('name', 'like', '%'.$term.'%'));
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyLikeFallback(Builder $query, string $term): void
    {
        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('sku', 'like', $term.'%')
                ->orWhere('short_description', 'like', '%'.$term.'%')
                ->orWhereHas('tags', fn (Builder $tags): Builder => $tags->where('name', 'like', '%'.$term.'%'));
        });
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applyFilters(Builder $query, CatalogFilters $filters): void
    {
        if ($filters->categoryIds !== []) {
            $query->whereIn('category_id', $filters->categoryIds);
        }

        if ($filters->brandIds !== []) {
            $query->whereIn('brand_id', $filters->brandIds);
        }

        if ($filters->minPriceCents !== null) {
            $query->where('price_cents', '>=', $filters->minPriceCents);
        }

        if ($filters->maxPriceCents !== null) {
            $query->where('price_cents', '<=', $filters->maxPriceCents);
        }

        if ($filters->onlyAvailable) {
            // Los productos sin control de inventario siempre estan disponibles.
            $query->where(function (Builder $inner): void {
                $inner->where('track_inventory', false)
                    ->orWhere('stock', '>', 0);
            });
        }

        if ($filters->onlyOnSale) {
            $query->whereNotNull('compare_at_price_cents')
                ->whereColumn('compare_at_price_cents', '>', 'price_cents');
        }
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function applySort(Builder $query, CatalogFilters $filters): void
    {
        match ($filters->sort) {
            'price_asc' => $query->orderBy('price_cents'),
            'price_desc' => $query->orderByDesc('price_cents'),
            'newest' => $query->latest('id'),
            'name' => $query->orderBy('name'),
            // Por relevancia: primero los destacados y luego por nombre. Sin un
            // termino de busqueda no hay relevancia que calcular, y este orden es
            // mas util que uno arbitrario.
            default => $query->orderByDesc('is_featured')->orderBy('name'),
        };

        // Desempate estable: sin el, dos productos con el mismo precio pueden
        // cambiar de lugar entre paginas y aparecer repetidos o desaparecer.
        $query->orderBy('id');
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], strict: true);
    }
}
