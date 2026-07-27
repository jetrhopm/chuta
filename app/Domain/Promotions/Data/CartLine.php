<?php

namespace App\Domain\Promotions\Data;

use App\Models\Product;

/**
 * Un renglon del carrito, ya con el precio que decide el servidor.
 *
 * El precio nunca viene del navegador: se toma del catalogo al construir esta
 * linea, de modo que el motor de descuentos trabaja sobre cifras confiables.
 */
readonly class CartLine
{
    public function __construct(
        public Product $product,
        public int $quantity,
        public int $unitPriceCents,
    ) {}

    public static function forProduct(Product $product, int $quantity): self
    {
        return new self($product, $quantity, $product->price_cents);
    }

    public function lineTotalCents(): int
    {
        return $this->unitPriceCents * $this->quantity;
    }

    /**
     * Los precios de cada pieza por separado.
     *
     * Hace falta para las promociones de tipo "compra X y recibe Y", que regalan
     * piezas y no porcentajes: hay que poder ordenar unidades individuales para
     * saber cuales son las mas baratas.
     *
     * @return array<int, int>
     */
    public function unitPrices(): array
    {
        return array_fill(0, $this->quantity, $this->unitPriceCents);
    }
}
