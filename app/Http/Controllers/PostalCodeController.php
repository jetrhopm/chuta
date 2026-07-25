<?php

namespace App\Http\Controllers;

use App\Domain\Addresses\PostalCodeLookup;
use Illuminate\Http\JsonResponse;

/**
 * Consulta asincrona de codigos postales para el checkout.
 *
 * Si el codigo no existe, responde 404 con un mensaje que la tienda muestra tal
 * cual para invitar a la captura manual. Nunca devuelve detalles internos.
 */
class PostalCodeController extends Controller
{
    public function __invoke(string $postcode, PostalCodeLookup $lookup): JsonResponse
    {
        $result = $lookup->find($postcode);

        if ($result === null) {
            return response()->json([
                'found' => false,
                'message' => 'No encontramos ese codigo postal; puedes escribir tu direccion manualmente.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'data' => $result,
        ]);
    }
}
