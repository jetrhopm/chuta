<?php

namespace App\Filament\Resources\Promotions\Schemas;

use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Promotions\Enums\DiscountType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromotionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Que es y como se activa')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre interno')
                        ->helperText('Solo lo ves tu, en reportes y en el historial de pedidos.')
                        ->required()
                        ->maxLength(160),
                    TextInput::make('description')
                        ->label('Texto para el cliente')
                        ->helperText('Se muestra en el carrito cuando la promocion aplica.')
                        ->maxLength(200),
                    Toggle::make('requires_code')
                        ->label('Necesita codigo (es un cupon)')
                        ->helperText('Sin codigo, la promocion se aplica sola a quien cumpla las condiciones.')
                        ->live(),
                    TextInput::make('code')
                        ->label('Codigo')
                        ->helperText('El cliente lo puede escribir como quiera: no importan mayusculas ni espacios.')
                        ->maxLength(60)
                        ->unique(ignoreRecord: true)
                        ->requiredIf('requires_code', true)
                        ->visible(fn (callable $get): bool => (bool) $get('requires_code')),
                    Toggle::make('is_active')
                        ->label('Activa')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('Descuento')
                ->schema([
                    Select::make('discount_type')
                        ->label('Tipo')
                        ->options(collect(DiscountType::cases())
                            ->mapWithKeys(fn (DiscountType $t): array => [$t->value => $t->label()])
                            ->all())
                        ->default(DiscountType::Percentage->value)
                        ->required()
                        ->live(),

                    TextInput::make('discount_value')
                        ->label(fn (callable $get): string => $get('discount_type') === DiscountType::Percentage->value
                            ? 'Porcentaje'
                            : 'Monto en centavos')
                        ->helperText(fn (callable $get): string => $get('discount_type') === DiscountType::Percentage->value
                            ? 'Ejemplo: 15 para un quince por ciento.'
                            : 'Ejemplo: 15000 para $150.00')
                        ->integer()
                        ->minValue(0)
                        ->required()
                        ->visible(fn (callable $get): bool => in_array(
                            $get('discount_type'),
                            [DiscountType::Percentage->value, DiscountType::FixedAmount->value],
                            true,
                        )),

                    TextInput::make('buy_quantity')
                        ->label('Piezas que compra')
                        ->helperText('Para un 3x2 pon 3.')
                        ->integer()
                        ->minValue(2)
                        ->required()
                        ->visible(fn (callable $get): bool => $get('discount_type') === DiscountType::BuyXGetY->value),

                    TextInput::make('get_quantity')
                        ->label('Piezas que se regalan')
                        ->helperText('Para un 3x2 pon 1. Se regala la pieza mas barata de cada grupo.')
                        ->integer()
                        ->minValue(1)
                        ->required()
                        ->visible(fn (callable $get): bool => $get('discount_type') === DiscountType::BuyXGetY->value),

                    TextInput::make('max_benefit_cents')
                        ->label('Beneficio maximo en centavos')
                        ->helperText('Opcional. Evita que un porcentaje sobre un carrito grande se vuelva un descuento sin limite.')
                        ->integer()
                        ->minValue(0),
                ])
                ->columns(2),

            Section::make('Condiciones')
                ->schema([
                    TextInput::make('min_subtotal_cents')
                        ->label('Subtotal minimo en centavos')
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    TextInput::make('min_quantity')
                        ->label('Cantidad minima de productos')
                        ->integer()
                        ->minValue(0)
                        ->default(0),
                    DateTimePicker::make('starts_at')
                        ->label('Empieza')
                        ->helperText('Vacio significa que ya esta vigente.')
                        ->seconds(false),
                    DateTimePicker::make('ends_at')
                        ->label('Termina')
                        ->helperText('Vacio significa que no vence.')
                        ->seconds(false)
                        ->after('starts_at'),
                    Toggle::make('first_purchase_only')
                        ->label('Solo primera compra'),
                    Toggle::make('allow_guests')
                        ->label('Permitir a clientes sin cuenta')
                        ->default(true),
                ])
                ->columns(2),

            Section::make('A que productos alcanza')
                ->description('Deja las listas vacias para que aplique a todo el catalogo. Las exclusiones ganan siempre.')
                ->schema([
                    // Las listas se guardan como JSON en la propia promocion, no
                    // como relaciones: son configuracion y se evaluan en memoria
                    // sobre las pocas promociones vigentes.
                    Select::make('product_ids')
                        ->label('Solo estos productos')
                        ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable(),
                    Select::make('category_ids')
                        ->label('Solo estas categorias')
                        ->options(fn (): array => Category::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Select::make('brand_ids')
                        ->label('Solo estas marcas')
                        ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->preload(),
                    Select::make('excluded_product_ids')
                        ->label('Excluir estos productos')
                        ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable(),
                ])
                ->columns(2),

            Section::make('Limites y combinacion')
                ->schema([
                    TextInput::make('priority')
                        ->label('Prioridad')
                        ->helperText('Menor numero se aplica antes.')
                        ->integer()
                        ->minValue(0)
                        ->default(100),
                    Toggle::make('is_exclusive')
                        ->label('Exclusiva')
                        ->helperText('Si aplica, ninguna otra promocion se aplica junto con ella.'),
                    TextInput::make('max_uses')
                        ->label('Usos totales')
                        ->helperText('Vacio para sin limite.')
                        ->integer()
                        ->minValue(1),
                    TextInput::make('max_uses_per_customer')
                        ->label('Usos por cliente')
                        ->helperText('Se cuenta por correo. Vacio para sin limite.')
                        ->integer()
                        ->minValue(1),
                    Select::make('payment_methods')
                        ->label('Solo con estos metodos de pago')
                        ->helperText('Vacio para permitir todos.')
                        ->options(collect(PaymentProvider::cases())
                            ->mapWithKeys(fn (PaymentProvider $p): array => [$p->value => $p->label()])
                            ->all())
                        ->multiple()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
