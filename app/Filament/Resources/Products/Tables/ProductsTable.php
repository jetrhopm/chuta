<?php

namespace App\Filament\Resources\Products\Tables;

use App\Domain\Inventory\Actions\RecordInventoryMovement;
use App\Domain\Inventory\Enums\InventoryMovementType;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('brand.name')
                    ->label('Marca')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Categoria')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Producto')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                TextColumn::make('price_cents')
                    ->label('Precio')
                    ->money('MXN', divideBy: 100, locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('compare_at_price_cents')
                    ->label('Precio anterior')
                    ->money('MXN', divideBy: 100, locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    // El color avisa de un problema de surtido sin obligar a
                    // abrir el producto para comparar con su umbral.
                    ->badge()
                    ->color(fn (Product $record): string => match (true) {
                        ! $record->track_inventory => 'gray',
                        $record->stock < 1 => 'danger',
                        $record->hasLowStock() => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (int $state, Product $record): string => $record->track_inventory
                        ? (string) $state
                        : 'Sin control'),
                TextColumn::make('stock_minimum')
                    ->label('Minimo')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_featured')
                    ->label('Destacado')
                    ->boolean(),
                // Un ToggleColumn ya es booleano por definicion: llamar a
                // ->boolean() aqui lanza BadMethodCallException en Filament 5.
                ToggleColumn::make('is_active')
                    ->label('Activo'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Activo'),
                TernaryFilter::make('is_featured')
                    ->label('Destacado'),
                Filter::make('stock_bajo')
                    ->label('Existencias bajas')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
                Filter::make('agotados')
                    ->label('Agotados')
                    ->query(fn (Builder $query): Builder => $query->where('track_inventory', true)->where('stock', '<', 1)),
            ])
            ->recordActions([
                self::adjustStockAction(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Ajuste manual de existencias.
     *
     * No escribe la columna directamente: pasa por la accion del dominio, que
     * bloquea la fila y deja el movimiento en el historial. Asi un ajuste hecho
     * a mano queda igual de trazable que una venta.
     */
    private static function adjustStockAction(): Action
    {
        return Action::make('ajustarExistencias')
            ->label('Ajustar existencias')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('warning')
            ->visible(fn (Product $record): bool => $record->track_inventory)
            ->authorize(fn (Product $record): bool => auth()->user()->can('adjustInventory', $record))
            ->schema([
                TextInput::make('quantity')
                    ->label('Piezas')
                    ->helperText('Positivo para agregar, negativo para retirar.')
                    ->integer()
                    ->required()
                    ->rule('not_in:0'),
                Textarea::make('reason')
                    ->label('Motivo')
                    ->helperText('Queda en el historial. Explica por que cambian las existencias.')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                try {
                    app(RecordInventoryMovement::class)->handle(
                        product: $record,
                        type: InventoryMovementType::Adjustment,
                        quantity: (int) $data['quantity'],
                        reason: $data['reason'],
                        user: auth()->user(),
                    );
                } catch (InsufficientStock $exception) {
                    Notification::make()
                        ->title('No se pudo ajustar')
                        ->body($exception->customerMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Existencias actualizadas')
                    ->body('Quedan '.$record->fresh()->stock.' piezas.')
                    ->success()
                    ->send();
            });
    }
}
