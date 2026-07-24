<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estado')
                    ->schema([
                        TextInput::make('code')
                            ->label('Folio')
                            ->disabled()
                            ->dehydrated(false),
                        Select::make('status')
                            ->label('Estado del pedido')
                            ->required()
                            ->options([
                                'pending_confirmation' => 'Pendiente de confirmar',
                                'confirmed' => 'Confirmado',
                                'preparing' => 'En preparacion',
                                'shipped' => 'Enviado',
                                'delivered' => 'Entregado',
                                'cancelled' => 'Cancelado',
                            ]),
                        Select::make('payment_method')
                            ->label('Metodo de pago')
                            ->disabled()
                            ->dehydrated(false)
                            ->options([
                                'bank_transfer' => 'Transferencia',
                                'card_on_delivery' => 'Tarjeta al recibir',
                                'cash_on_delivery' => 'Efectivo al recibir',
                            ]),
                        Select::make('payment_status')
                            ->label('Estado de pago')
                            ->required()
                            ->options([
                                'pending' => 'Pendiente',
                                'paid' => 'Pagado',
                                'failed' => 'Fallido',
                                'refunded' => 'Reembolsado',
                            ]),
                        TextInput::make('subtotal_cents')
                            ->label('Subtotal en centavos')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('shipping_cents')
                            ->label('Envio en centavos')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('total_cents')
                            ->label('Total en centavos')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(3),
                Section::make('Cliente y envio')
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Cliente')
                            ->required(),
                        TextInput::make('customer_email')
                            ->label('Correo')
                            ->email(),
                        TextInput::make('customer_phone')
                            ->label('Telefono')
                            ->tel()
                            ->required(),
                        TextInput::make('shipping_street')
                            ->label('Calle')
                            ->required(),
                        TextInput::make('shipping_number')
                            ->label('Numero'),
                        TextInput::make('shipping_neighborhood')
                            ->label('Colonia')
                            ->required(),
                        TextInput::make('shipping_city')
                            ->label('Ciudad')
                            ->required(),
                        TextInput::make('shipping_state')
                            ->label('Estado')
                            ->required(),
                        TextInput::make('shipping_postcode')
                            ->label('CP')
                            ->required(),
                        Textarea::make('shipping_reference')
                            ->label('Referencia')
                            ->columnSpanFull(),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
