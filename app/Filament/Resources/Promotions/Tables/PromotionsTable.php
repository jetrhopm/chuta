<?php

namespace App\Filament\Resources\Promotions\Tables;

use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Promotion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('code')
                    ->label('Codigo')
                    ->searchable()
                    ->placeholder('Automatica')
                    ->badge()
                    ->color('info'),
                TextColumn::make('discount_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (DiscountType $state): string => $state->label()),
                TextColumn::make('discount_value')
                    ->label('Valor')
                    // El mismo campo significa porcentaje o centavos segun el tipo,
                    // asi que se formatea distinto para que no se lea mal.
                    ->formatStateUsing(fn (int $state, Promotion $record): string => match ($record->discount_type) {
                        DiscountType::Percentage => $state.'%',
                        DiscountType::FixedAmount => '$'.number_format($state / 100, 2),
                        DiscountType::BuyXGetY => $record->buy_quantity.'x'.max(0, (int) $record->buy_quantity - (int) $record->get_quantity),
                        DiscountType::FreeShipping => 'Envio gratis',
                    }),
                TextColumn::make('uses_count')
                    ->label('Usos')
                    ->formatStateUsing(fn (int $state, Promotion $record): string => $record->max_uses === null
                        ? (string) $state
                        : $state.' de '.$record->max_uses)
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Vence')
                    ->dateTime('d/M/Y H:i')
                    ->placeholder('Sin vencimiento')
                    ->sortable(),
                IconColumn::make('is_exclusive')
                    ->label('Exclusiva')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_active')
                    ->label('Activa'),
            ])
            ->filters([
                TernaryFilter::make('requires_code')
                    ->label('Cupones con codigo'),
                TernaryFilter::make('is_active')
                    ->label('Activa'),
                SelectFilter::make('discount_type')
                    ->label('Tipo')
                    ->options(collect(DiscountType::cases())
                        ->mapWithKeys(fn (DiscountType $t): array => [$t->value => $t->label()])
                        ->all()),
                Filter::make('vigentes')
                    ->label('Vigentes ahora')
                    ->query(fn (Builder $query): Builder => $query->currentlyValid()),
                Filter::make('agotadas')
                    ->label('Agotadas')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNotNull('max_uses')
                        ->whereColumn('uses_count', '>=', 'max_uses')),
            ])
            ->recordActions([
                EditAction::make(),
                // Solo aparece en las que nunca se usaron: la Policy niega borrar
                // una promocion con usos, porque eso eliminaria el registro que
                // sostiene sus limites.
                DeleteAction::make(),
            ])
            ->toolbarActions([]);
    }
}
