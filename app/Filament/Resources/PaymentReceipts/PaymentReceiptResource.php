<?php

namespace App\Filament\Resources\PaymentReceipts;

use App\Filament\Resources\PaymentReceipts\Pages\ListPaymentReceipts;
use App\Filament\Resources\PaymentReceipts\Tables\PaymentReceiptsTable;
use App\Models\PaymentReceipt;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Comprobantes de transferencia pendientes de revision.
 *
 * No tiene pantallas de creacion ni de edicion: los comprobantes los sube el
 * cliente, y lo unico que se hace aqui es aceptarlos o rechazarlos.
 */
class PaymentReceiptResource extends Resource
{
    protected static ?string $model = PaymentReceipt::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?string $navigationLabel = 'Comprobantes';

    protected static ?string $modelLabel = 'comprobante';

    protected static ?string $pluralModelLabel = 'comprobantes';

    protected static string|UnitEnum|null $navigationGroup = 'Pedidos';

    public static function table(Table $table): Table
    {
        return PaymentReceiptsTable::configure($table);
    }

    /**
     * Cuantos esperan revision, para que no se queden olvidados.
     */
    public static function getNavigationBadge(): ?string
    {
        $pendientes = PaymentReceipt::where('status', PaymentReceipt::STATUS_PENDING)->count();

        return $pendientes > 0 ? (string) $pendientes : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentReceipts::route('/'),
        ];
    }
}
