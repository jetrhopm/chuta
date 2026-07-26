<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentReceipt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Comprobantes de transferencia.
 *
 * El archivo va a un disco privado y con nombre aleatorio: un comprobante lleva
 * datos bancarios y no debe quedar accesible con solo adivinar su direccion. Para
 * verlo desde el panel se sirve por una ruta que exige permiso.
 */
class PaymentReceiptController extends Controller
{
    public function store(Request $request, string $code): RedirectResponse
    {
        $order = Order::where('code', $code)->firstOrFail();

        $validated = $request->validate([
            'receipt' => [
                'required',
                'file',
                // Se valida el tipo real del archivo, no su extension.
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
                'max:5120',
            ],
        ], [
            'receipt.mimetypes' => 'El comprobante debe ser una imagen o un PDF.',
            'receipt.max' => 'El comprobante no puede pesar mas de 5 MB.',
        ]);

        $file = $validated['receipt'];

        // Nombre aleatorio: el original puede traer datos de la persona y podria
        // usarse para adivinar otras direcciones.
        $path = $file->storeAs(
            'receipts/'.$order->code,
            Str::random(40).'.'.$file->extension(),
            'local',
        );

        PaymentReceipt::create([
            'order_id' => $order->getKey(),
            'path' => $path,
            'original_name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'mime_type' => (string) $file->getMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'status' => PaymentReceipt::STATUS_PENDING,
        ]);

        return redirect()
            ->to($order->trackingUrl())
            ->with('receipt_uploaded', true);
    }

    /**
     * Entrega el archivo a quien tenga permiso de administrar pedidos.
     *
     * No se sirve desde el disco publico a proposito: asi el acceso pasa por la
     * autorizacion en lugar de depender de que nadie adivine la direccion.
     */
    public function show(PaymentReceipt $receipt): StreamedResponse
    {
        $this->authorize('view', $receipt->order);

        abort_unless(Storage::disk('local')->exists($receipt->path), 404);

        return Storage::disk('local')->response(
            $receipt->path,
            $receipt->original_name,
            ['Content-Type' => $receipt->mime_type],
        );
    }
}
