<?php

namespace App\Filament\Resources\PaymentReceipts\Tables;

use App\Domain\Payments\Actions\ReviewPaymentReceipt;
use App\Models\PaymentReceipt;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order:id,code,total_cents,payment_status', 'reviewer:id,name']))
            // Los pendientes primero: son los que hay que atender.
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recibido')
                    ->dateTime('d/M/Y H:i')
                    ->sortable(),
                TextColumn::make('order.code')
                    ->label('Pedido')
                    ->searchable(),
                TextColumn::make('order.total_cents')
                    ->label('Total del pedido')
                    ->money('MXN', divideBy: 100, locale: 'es_MX'),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state, PaymentReceipt $record): string => $record->statusLabel())
                    ->color(fn (string $state): string => match ($state) {
                        PaymentReceipt::STATUS_ACCEPTED => 'success',
                        PaymentReceipt::STATUS_REJECTED => 'danger',
                        default => 'warning',
                    }),
                TextColumn::make('original_name')
                    ->label('Archivo')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('size_bytes')
                    ->label('Peso')
                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 0).' KB')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewer.name')
                    ->label('Revisado por')
                    ->placeholder('Sin revisar'),
                TextColumn::make('review_comment')
                    ->label('Comentario')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        PaymentReceipt::STATUS_PENDING => 'Pendiente de revision',
                        PaymentReceipt::STATUS_ACCEPTED => 'Aceptado',
                        PaymentReceipt::STATUS_REJECTED => 'Rechazado',
                    ])
                    ->default(PaymentReceipt::STATUS_PENDING),
            ])
            ->recordActions([
                self::viewFileAction(),
                self::acceptAction(),
                self::rejectAction(),
            ])
            ->toolbarActions([]);
    }

    /**
     * Abre el archivo por la ruta que exige autorizacion.
     *
     * No se enlaza al disco directamente: el comprobante lleva datos bancarios y
     * el acceso tiene que pasar por la comprobacion de permisos.
     */
    private static function viewFileAction(): Action
    {
        return Action::make('verArchivo')
            ->label('Ver comprobante')
            ->icon(Heroicon::OutlinedEye)
            ->url(fn (PaymentReceipt $record): string => route('receipts.show', ['receipt' => $record->getKey()]))
            ->openUrlInNewTab();
    }

    private static function acceptAction(): Action
    {
        return Action::make('aceptar')
            ->label('Aceptar')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Aceptar el comprobante')
            ->modalDescription('El pago del pedido quedara aprobado y se confirmara el pedido. Revisa que el deposito coincida con el total antes de continuar.')
            ->modalSubmitActionLabel('Si, el pago es correcto')
            ->visible(fn (PaymentReceipt $record): bool => $record->isPending())
            ->authorize(fn (PaymentReceipt $record): bool => auth()->user()->can('review', $record))
            ->schema([
                Textarea::make('comment')
                    ->label('Comentario para el cliente')
                    ->helperText('Opcional. Se muestra en la pagina de su pedido.')
                    ->maxLength(500),
            ])
            ->action(function (PaymentReceipt $record, array $data): void {
                app(ReviewPaymentReceipt::class)->accept(
                    receipt: $record,
                    reviewer: auth()->user(),
                    comment: $data['comment'] ?: null,
                );

                Notification::make()
                    ->title('Comprobante aceptado')
                    ->body('El pago quedo aprobado y el pedido confirmado.')
                    ->success()
                    ->send();
            });
    }

    private static function rejectAction(): Action
    {
        return Action::make('rechazar')
            ->label('Rechazar')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Rechazar el comprobante')
            // El pedido no se cancela: lo normal es que el cliente haya subido un
            // archivo equivocado y pueda mandar otro.
            ->modalDescription('El pedido seguira pendiente de pago para que el cliente pueda subir otro comprobante.')
            ->modalSubmitActionLabel('Rechazar')
            ->visible(fn (PaymentReceipt $record): bool => $record->isPending())
            ->authorize(fn (PaymentReceipt $record): bool => auth()->user()->can('review', $record))
            ->schema([
                Textarea::make('comment')
                    ->label('Motivo')
                    ->helperText('Se muestra al cliente para que sepa que corregir.')
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (PaymentReceipt $record, array $data): void {
                app(ReviewPaymentReceipt::class)->reject(
                    receipt: $record,
                    reviewer: auth()->user(),
                    comment: $data['comment'],
                );

                Notification::make()
                    ->title('Comprobante rechazado')
                    ->body('El cliente vera el motivo en la pagina de su pedido.')
                    ->warning()
                    ->send();
            });
    }
}
