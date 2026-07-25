<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use App\Domain\Inventory\Enums\InventoryMovementType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Sin esto cada renglon dispararia consultas aparte para el producto,
            // el pedido y el usuario.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product:id,name,sku', 'order:id,code', 'user:id,name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/M/Y H:i')
                    ->sortable(),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('product.sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (InventoryMovementType $state): string => $state->label())
                    ->color(fn (InventoryMovementType $state): string => match ($state) {
                        InventoryMovementType::Sale => 'danger',
                        InventoryMovementType::Adjustment => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('quantity')
                    ->label('Piezas')
                    // El signo se muestra siempre: es la diferencia entre una
                    // entrada y una salida, y leer "5" a secas es ambiguo.
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state)
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('stock_after')
                    ->label('Existencias despues')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('order.code')
                    ->label('Pedido')
                    ->searchable()
                    ->placeholder('Sin pedido'),
                TextColumn::make('user.name')
                    ->label('Responsable')
                    // Una venta de la tienda no la hizo nadie del panel.
                    ->placeholder('Venta automatica')
                    ->toggleable(),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(fn (): array => collect(InventoryMovementType::cases())
                        ->mapWithKeys(fn (InventoryMovementType $type): array => [$type->value => $type->label()])
                        ->all()),
                SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('solo_salidas')
                    ->label('Solo salidas')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '<', 0)),
                Filter::make('solo_entradas')
                    ->label('Solo entradas')
                    ->query(fn (Builder $query): Builder => $query->where('quantity', '>', 0)),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
