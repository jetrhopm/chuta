<?php

namespace App\Http\Controllers;

use App\Domain\Payments\Actions\ProcessPaymentWebhook;
use App\Domain\Payments\Enums\PaymentProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada de los avisos de los proveedores de pago.
 *
 * Responde 200 tambien cuando descarta el aviso, para que el proveedor no lo
 * reintente en bucle por algo que no va a cambiar. Un aviso rechazado queda
 * registrado con su firma marcada como invalida, que es lo que sirve para
 * investigarlo despues.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, ProcessPaymentWebhook $process): JsonResponse
    {
        $enum = PaymentProvider::tryFrom($provider);

        if ($enum === null) {
            return response()->json(['received' => false], 404);
        }

        // El cuerpo crudo, sin pasar por el decodificador: la firma se calcula
        // sobre los bytes exactos que envio el proveedor, y volver a serializar el
        // arreglo cambiaria el resultado.
        $rawBody = $request->getContent();

        $accepted = $process->handle(
            provider: $enum,
            rawBody: $rawBody,
            payload: $request->json()->all(),
            headers: $request->headers->all(),
        );

        return response()->json(['received' => true, 'processed' => $accepted]);
    }
}
