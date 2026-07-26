<?php

namespace App\Filament\Resources\PaymentReceipts\Pages;

use App\Filament\Resources\PaymentReceipts\PaymentReceiptResource;
use Filament\Resources\Pages\ListRecords;

class ListPaymentReceipts extends ListRecords
{
    protected static string $resource = PaymentReceiptResource::class;

    /**
     * Sin accion de creacion: los comprobantes los sube el cliente.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
