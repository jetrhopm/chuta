<?php

namespace App\Domain\Catalog\Data;

/**
 * Filtros del catalogo, tomados de la URL.
 *
 * Se construyen desde la peticion para que los filtros vivan en la direccion y se
 * puedan compartir y volver a abrir. Cada valor se sanea aqui, de modo que el
 * resto del codigo trabaja con datos ya limpios.
 */
readonly class CatalogFilters
{
    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $brandIds
     */
    public function __construct(
        public ?string $term = null,
        public array $categoryIds = [],
        public array $brandIds = [],
        public ?int $minPriceCents = null,
        public ?int $maxPriceCents = null,
        public bool $onlyAvailable = false,
        public bool $onlyOnSale = false,
        public string $sort = 'relevance',
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public static function fromQuery(array $query): self
    {
        return new self(
            term: self::cleanTerm($query['q'] ?? null),
            categoryIds: self::intList($query['categoria'] ?? null),
            brandIds: self::intList($query['marca'] ?? null),
            // Los precios llegan en pesos porque es lo que el cliente entiende, y
            // se guardan en centavos como todo el dinero del proyecto.
            minPriceCents: self::pesosToCents($query['precio_min'] ?? null),
            maxPriceCents: self::pesosToCents($query['precio_max'] ?? null),
            onlyAvailable: filter_var($query['disponibles'] ?? false, FILTER_VALIDATE_BOOLEAN),
            onlyOnSale: filter_var($query['ofertas'] ?? false, FILTER_VALIDATE_BOOLEAN),
            sort: self::cleanSort($query['orden'] ?? null),
        );
    }

    public function hasTerm(): bool
    {
        return $this->term !== null && $this->term !== '';
    }

    public function isEmpty(): bool
    {
        return ! $this->hasTerm()
            && $this->categoryIds === []
            && $this->brandIds === []
            && $this->minPriceCents === null
            && $this->maxPriceCents === null
            && ! $this->onlyAvailable
            && ! $this->onlyOnSale;
    }

    /**
     * Los filtros activos, para reconstruir la direccion sin arrastrar vacios.
     *
     * @return array<string, mixed>
     */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->term,
            'categoria' => $this->categoryIds === [] ? null : implode(',', $this->categoryIds),
            'marca' => $this->brandIds === [] ? null : implode(',', $this->brandIds),
            'precio_min' => $this->minPriceCents === null ? null : $this->minPriceCents / 100,
            'precio_max' => $this->maxPriceCents === null ? null : $this->maxPriceCents / 100,
            'disponibles' => $this->onlyAvailable ? 1 : null,
            'ofertas' => $this->onlyOnSale ? 1 : null,
            'orden' => $this->sort === 'relevance' ? null : $this->sort,
        ], static fn ($value): bool => $value !== null && $value !== '');
    }

    private static function cleanTerm(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $term = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        // Un termino de una sola letra devuelve casi todo el catalogo y no ayuda.
        return mb_strlen($term) < 2 ? null : mb_substr($term, 0, 80);
    }

    /**
     * @return array<int, int>
     */
    private static function intList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(static fn ($item): int => (int) $item, $value),
            static fn (int $id): bool => $id > 0,
        )));
    }

    private static function pesosToCents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $cents = (int) round(((float) $value) * 100);

        return $cents >= 0 ? $cents : null;
    }

    private static function cleanSort(mixed $value): string
    {
        $permitidos = ['relevance', 'price_asc', 'price_desc', 'newest', 'name'];

        return is_string($value) && in_array($value, $permitidos, strict: true)
            ? $value
            : 'relevance';
    }
}
