<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos principales')
                    ->schema([
                        Select::make('brand_id')
                            ->label('Marca')
                            ->relationship('brand', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('short_description')
                            ->label('Descripcion corta')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripcion')
                            ->rows(5)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Precio, inventario y visibilidad')
                    ->schema([
                        TextInput::make('price_cents')
                            ->label('Precio')
                            ->helperText('Captura el importe en centavos. Ejemplo: 89900 = $899.00')
                            ->required()
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('compare_at_price_cents')
                            ->label('Precio anterior')
                            ->helperText('Opcional, tambien en centavos.')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('stock')
                            ->label('Existencias')
                            // Solo al crear. Despues las existencias se mueven
                            // con "Ajustar existencias", que deja constancia en
                            // el historial; editarlas aqui las cambiaria sin
                            // dejar rastro de quien lo hizo ni por que.
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'Existencias iniciales.'
                                : 'Para cambiarlas usa "Ajustar existencias" en el listado, para que quede en el historial.')
                            ->disabled(fn (string $operation): bool => $operation !== 'create')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('stock_minimum')
                            ->label('Existencias minimas')
                            ->helperText('Avisa cuando queden estas piezas o menos. Cero desactiva el aviso.')
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('track_inventory')
                            ->label('Llevar inventario')
                            ->helperText('Si se desactiva, el producto se puede vender sin tope.')
                            ->default(true),
                        Toggle::make('is_featured')
                            ->label('Destacado')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->required(),
                        Textarea::make('image_path')
                            ->label('URL o ruta de imagen')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
