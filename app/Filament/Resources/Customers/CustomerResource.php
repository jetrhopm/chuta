<?php

namespace App\Filament\Resources\Customers;

use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cliente')
                ->schema([
                    TextInput::make('name')->label('Nombre')->disabled(),
                    TextInput::make('email')->label('Correo')->disabled(),
                    TextInput::make('phone')->label('Telefono')->disabled(),
                    TextInput::make('orders_count')->label('Pedidos')->disabled(),
                    TextInput::make('lifetime_value_cents')->label('Valor total en centavos')->disabled(),
                    TextInput::make('last_order_at')->label('Ultimo pedido')->disabled(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lifetime_value_cents')
                    ->label('Valor')
                    ->money('MXN', divideBy: 100, locale: 'es_MX')
                    ->sortable(),
                TextColumn::make('last_order_at')
                    ->label('Ultimo pedido')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_order_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'edit' => EditCustomer::route('/{record}/edit'),
        ];
    }
}
