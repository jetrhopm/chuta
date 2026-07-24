<?php

namespace App\Filament\Resources\Orders\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
