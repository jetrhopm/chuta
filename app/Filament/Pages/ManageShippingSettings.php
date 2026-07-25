<?php

namespace App\Filament\Pages;

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Shipping\Data\ShippingSettings;
use App\Domain\Shipping\ShippingSettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Configuracion de envios.
 *
 * Todo lo que afecta al costo del envio se ajusta aqui, sin tocar codigo ni el
 * archivo de entorno. Una vez sembrada, esta configuracion es la fuente de
 * verdad: el checkout la lee para calcular lo que cobra.
 */
class ManageShippingSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static ?string $navigationLabel = 'Envios';

    protected static string|UnitEnum|null $navigationGroup = 'Configuracion';

    protected string $view = 'filament.pages.manage-shipping-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        // Los envios cambian lo que se cobra, asi que se tratan como una opcion
        // comercial y no como contenido.
        return auth()->user()?->can(AdminPermission::ManageOrders->value) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Configuracion de envios';
    }

    public function mount(): void
    {
        // No hace falta autorizar a mano: Filament llama a canAccess() al montar
        // la pagina y en cada hidratacion de Livewire, asi que tanto la carga
        // como el guardado quedan cubiertos.
        $settings = app(ShippingSettingsRepository::class)->get();

        $this->form->fill([
            ...$settings->toArray(),
            // Las listas se muestran una por linea, que es como se leen y se
            // pegan con comodidad.
            'excluded_states' => implode("\n", $settings->excludedStates),
            'excluded_postcodes' => implode("\n", $settings->excludedPostcodes),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Metodo de envio')
                    ->description('Si se desactiva, la tienda deja de aceptar pedidos con envio y lo explica al cliente.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Envios activos'),
                        TextInput::make('method_name')
                            ->label('Nombre visible del metodo')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('flat_cents')
                            ->label('Tarifa unica nacional')
                            ->helperText('En centavos. Ejemplo: 9900 = $99.00')
                            ->required()
                            ->integer()
                            ->minValue(0),
                        TextInput::make('preparation_days')
                            ->label('Dias de preparacion')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(60),
                        Textarea::make('delivery_estimate')
                            ->label('Mensaje de entrega estimada')
                            ->helperText('Se muestra al cliente en el carrito y el checkout.')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Envio gratis')
                    ->schema([
                        Toggle::make('free_shipping_enabled')
                            ->label('Ofrecer envio gratis')
                            ->live(),
                        TextInput::make('free_shipping_threshold_cents')
                            ->label('Umbral de envio gratis')
                            ->helperText('En centavos. Ejemplo: 80000 = $800.00')
                            ->required()
                            ->integer()
                            ->minValue(0)
                            ->visible(fn (callable $get): bool => (bool) $get('free_shipping_enabled')),
                        Toggle::make('threshold_after_discounts')
                            ->label('Comparar el umbral despues de descuentos')
                            ->helperText('Activado, un cupon puede dejar el pedido por debajo del umbral y cobrar envio.')
                            ->visible(fn (callable $get): bool => (bool) $get('free_shipping_enabled')),
                    ])
                    ->columns(2),

                Section::make('Cobertura')
                    ->description('Deja los campos vacios para enviar a todo el pais.')
                    ->schema([
                        Textarea::make('excluded_states')
                            ->label('Estados sin cobertura')
                            ->helperText('Uno por linea. No importan los acentos ni las mayusculas.')
                            ->rows(4),
                        Textarea::make('excluded_postcodes')
                            ->label('Codigos postales sin cobertura')
                            ->helperText('Uno por linea, de cinco digitos.')
                            ->rows(4),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        app(ShippingSettingsRepository::class)->save(ShippingSettings::fromArray($data));

        Notification::make()
            ->title('Configuracion de envios guardada')
            ->body('La tienda ya calcula el envio con estos valores.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Guardar cambios')
                ->submit('save'),
        ];
    }
}
