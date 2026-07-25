<?php

namespace App\Domain\Addresses;

use App\Models\PostalCode;
use Illuminate\Support\Facades\Cache;

/**
 * Consulta de codigos postales contra el catalogo local.
 *
 * La fuente es la tabla local importada del catalogo oficial, no una API de
 * terceros: asi la captura de direcciones no depende de que un servicio ajeno
 * este disponible.
 */
class PostalCodeLookup
{
    private const CACHE_PREFIX = 'postal-code:';

    private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 7;

    /**
     * Devuelve todos los asentamientos de un codigo postal.
     *
     * Todos, no una seleccion: un codigo postal puede tener decenas de colonias
     * y recortar la lista dejaria al cliente sin poder elegir la suya.
     *
     * @return array{postcode: string, state: string, municipality: string, city: string|null, settlements: array<int, array{name: string, type: string|null, zone: string|null}>}|null
     */
    public function find(string $postcode): ?array
    {
        $postcode = $this->normalize($postcode);

        if ($postcode === null) {
            return null;
        }

        return Cache::remember(
            self::CACHE_PREFIX.$postcode,
            self::CACHE_TTL_SECONDS,
            fn (): ?array => $this->query($postcode),
        );
    }

    public function flushCache(): void
    {
        // No hay etiquetas con el driver de base de datos, asi que se limpia la
        // cache completa. Solo ocurre al reimportar el catalogo.
        Cache::flush();
    }

    /**
     * @return array{postcode: string, state: string, municipality: string, city: string|null, settlements: array<int, array{name: string, type: string|null, zone: string|null}>}|null
     */
    private function query(string $postcode): ?array
    {
        $rows = PostalCode::query()
            ->where('postcode', $postcode)
            ->orderBy('settlement')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $first = $rows->first();

        return [
            'postcode' => $postcode,
            'state' => $first->state,
            'municipality' => $first->municipality,
            'city' => $first->city,
            'settlements' => $rows->map(fn (PostalCode $row): array => [
                'name' => $row->settlement,
                'type' => $row->settlement_type,
                'zone' => $row->zone,
            ])->values()->all(),
        ];
    }

    private function normalize(string $postcode): ?string
    {
        $digits = preg_replace('/\D/', '', trim($postcode)) ?? '';

        return strlen($digits) === 5 ? $digits : null;
    }
}
