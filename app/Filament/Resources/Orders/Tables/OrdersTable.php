<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domain\Inventory\Actions\RestockOrder;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Folio')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Pedido')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Pago')
                    ->searchable(),
                TextColumn::make('payment_status')
                    ->label('Estado pago')
                    ->badge()
                    ->searchable(),
                TextColumn::make('subtotal_cents')
                    ->label('Subtotal')
                    ->money('MXN', divideBy: 100, locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('shipping_cents')
                    ->label('Envio')
                    ->money('MXN', divideBy: 100, locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->money('MXN', divideBy: 100, locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable(),
                TextColumn::make('customer.orders_count')
                    ->label('Compras cliente')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('customer_email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('customer_phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('shipping_street')
                    ->label('Calle')
                    ->searchable(),
                TextColumn::make('shipping_number')
                    ->searchable(),
                TextColumn::make('shipping_neighborhood')
                    ->searchable(),
                TextColumn::make('shipping_city')
                    ->searchable(),
                TextColumn::make('shipping_state')
                    ->searchable(),
                TextColumn::make('shipping_postcode')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado de pedido')
                    ->options([
                        'pending_confirmation' => 'Pendiente de confirmar',
                        'confirmed' => 'Confirmado',
                        'preparing' => 'En preparacion',
                        'shipped' => 'Enviado',
                        'delivered' => 'Entregado',
                        'cancelled' => 'Cancelado',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('Estado de pago')
                    ->options([
                        'pending' => 'Pendiente',
                        'paid' => 'Pagado',
                        'failed' => 'Fallido',
                        'refunded' => 'Reembolsado',
                    ]),
                SelectFilter::make('payment_method')
                    ->label('Metodo')
                    ->options([
                        'bank_transfer' => 'Transferencia',
                        'card_on_delivery' => 'Tarjeta al recibir',
                        'cash_on_delivery' => 'Efectivo al recibir',
                    ]),
            ])
            ->recordActions([
                self::cancelAction(),
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Cancela el pedido y repone su inventario.
     *
     * La reposicion es controlada: solo devuelve lo que de verdad se habia
     * descontado y nunca dos veces el mismo pedido, asi que cancelar por error
     * no puede inflar las existencias.
     */
    private static function cancelAction(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancelar pedido')
            ->modalDescription('Se repondra al inventario lo que este pedido habia descontado. La accion queda registrada.')
            ->modalSubmitActionLabel('Si, cancelar el pedido')
            ->visible(fn (Order $record): bool => $record->status !== 'cancelled')
            ->authorize(fn (Order $record): bool => auth()->user()->can('cancel', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda en el historial de inventario.')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Order $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    app(RestockOrder::class)->handle(
                        order: $record->load('items.product'),
                        type: InventoryMovementType::Cancellation,
                        reason: $data['reason'],
                        user: auth()->user(),
                    );

                    $record->forceFill(['status' => 'cancelled'])->save();
                });

                Notification::make()
                    ->title('Pedido cancelado')
                    ->body('El inventario quedo repuesto.')
                    ->success()
                    ->send();
            });
    }
}
