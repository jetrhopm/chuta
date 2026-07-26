<?php

namespace App\Filament\Pages;

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Payments\Enums\PaymentProvider;
use App\Domain\Payments\PaymentGatewayRegistry;
use App\Domain\Payments\Settings\GatewaySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
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
 * Configuracion de los proveedores de pago.
 *
 * Los secretos se muestran enmascarados y solo se sobrescriben si se captura un
 * valor nuevo: guardar la pantalla tal como se ve no debe destruir la credencial
 * buena. La prueba de conexion es de solo lectura y nunca genera un cobro.
 */
class ManagePaymentIntegrations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Pagos';

    protected static string|UnitEnum|null $navigationGroup = 'Configuracion';

    protected string $view = 'filament.pages.manage-payment-integrations';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(AdminPermission::ManagePaymentProviders->value) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Proveedores de pago';
    }

    public function mount(): void
    {
        $settings = app(GatewaySettings::class);
        $valores = [];

        foreach (PaymentProvider::cases() as $provider) {
            // Los secretos llegan enmascarados: la pantalla debe poder mostrar que
            // hay una llave configurada sin devolverla completa al navegador.
            $valores[$provider->value] = $settings->forDisplay($provider);
        }

        // La direccion del webhook se calcula aqui y no con el valor por omision
        // del campo, porque al rellenar el formulario ese valor no se aplica.
        $valores[PaymentProvider::Clip->value]['webhook_url'] = route('payments.webhook', [
            'provider' => PaymentProvider::Clip->value,
        ]);

        $this->form->fill($valores);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                $this->clipSection(),
                $this->bankTransferSection(),
                $this->pendingSection(),
            ]);
    }

    private function clipSection(): Section
    {
        return Section::make('Clip')
            ->description('Checkout redireccionado. El cobro ocurre en la pantalla de Clip y este servidor nunca recibe datos de tarjeta.')
            ->schema([
                Toggle::make('clip.enabled')
                    ->label('Activo'),
                Toggle::make('clip.sandbox')
                    ->label('Modo pruebas')
                    ->helperText('Desactivalo solo cuando vayas a cobrar de verdad.')
                    ->default(true),
                TextInput::make('clip.api_key')
                    ->label('API key')
                    ->helperText('Dejalo vacio para conservar la que ya esta guardada.')
                    ->password()
                    ->revealable()
                    ->autocomplete(false),
                TextInput::make('clip.secret_key')
                    ->label('Clave secreta')
                    ->helperText('Dejalo vacio para conservar la que ya esta guardada.')
                    ->password()
                    ->revealable()
                    ->autocomplete(false),
                TextInput::make('clip.webhook_secret')
                    ->label('Secreto del webhook')
                    ->helperText('Sin este valor los avisos de Clip se rechazan, porque no se pueden verificar.')
                    ->password()
                    ->revealable()
                    ->autocomplete(false),
                Toggle::make('clip.refunds_enabled')
                    ->label('Mi cuenta permite reembolsos por API')
                    ->helperText('Activalo solo si Clip lo habilito para tu cuenta.'),
                TextInput::make('clip.webhook_url')
                    ->label('URL del webhook')
                    ->helperText('Registra esta direccion en el panel de Clip.')
                    ->disabled()
                    ->dehydrated(false)
                    ->default(fn (): string => route('payments.webhook', ['provider' => 'clip']))
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private function bankTransferSection(): Section
    {
        return Section::make('Transferencia bancaria')
            ->description('Se revisa a mano: el cliente sube su comprobante y una persona lo aprueba desde Pedidos > Comprobantes.')
            ->schema([
                Toggle::make('bank_transfer.enabled')
                    ->label('Activo'),
                TextInput::make('bank_transfer.bank')
                    ->label('Banco')
                    ->maxLength(120),
                TextInput::make('bank_transfer.account_holder')
                    ->label('Beneficiario')
                    ->maxLength(160),
                TextInput::make('bank_transfer.clabe')
                    ->label('CLABE')
                    ->helperText('18 digitos.')
                    ->maxLength(24),
                TextInput::make('bank_transfer.account_number')
                    ->label('Numero de cuenta')
                    ->helperText('Opcional.')
                    ->maxLength(40),
                TextInput::make('bank_transfer.expires_in_hours')
                    ->label('Horas para pagar')
                    ->helperText('Cero para no poner limite.')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(720),
                Textarea::make('bank_transfer.instructions')
                    ->label('Instrucciones adicionales')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Proveedores sin adaptador todavia.
     *
     * Se muestran diciendolo con claridad, en lugar de ofrecer campos que no
     * harian nada.
     */
    private function pendingSection(): Section
    {
        $pendientes = collect(app(PaymentGatewayRegistry::class)->pending())
            ->map(fn (PaymentProvider $p): string => $p->label())
            ->implode(' y ');

        return Section::make('Todavia no disponibles')
            ->description($pendientes === ''
                ? 'Todos los proveedores tienen adaptador.'
                : $pendientes.': la integracion aun no esta implementada, asi que no aparecen en el checkout.')
            ->schema([]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $settings = app(GatewaySettings::class);

        foreach ($data as $providerValue => $valores) {
            $provider = PaymentProvider::tryFrom((string) $providerValue);

            if ($provider === null || ! is_array($valores)) {
                continue;
            }

            // Un secreto que sigue enmascarado significa "no lo cambies": se
            // descarta antes de guardar para no escribir los asteriscos encima de
            // la credencial buena.
            $settings->save($provider, $this->withoutMaskedSecrets($valores));
        }

        Notification::make()
            ->title('Configuracion guardada')
            ->success()
            ->send();

        $this->mount();
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function withoutMaskedSecrets(array $valores): array
    {
        foreach ($valores as $clave => $valor) {
            if (is_string($valor) && str_starts_with($valor, '************')) {
                $valores[$clave] = '';
            }
        }

        return $valores;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->testConnectionAction(),
            $this->forgetCredentialsAction(),
        ];
    }

    private function testConnectionAction(): Action
    {
        return Action::make('probarConexion')
            ->label('Probar conexion')
            ->icon(Heroicon::OutlinedSignal)
            ->color('gray')
            ->schema([
                Select::make('provider')
                    ->label('Proveedor')
                    ->options($this->providerOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $provider = PaymentProvider::from($data['provider']);
                $gateway = app(PaymentGatewayRegistry::class)->tryGet($provider);

                if ($gateway === null) {
                    Notification::make()
                        ->title('Sin adaptador')
                        ->body('Ese proveedor todavia no esta implementado.')
                        ->warning()
                        ->send();

                    return;
                }

                // Solo lectura: la prueba nunca genera un cobro real.
                $result = $gateway->testConnection();

                Notification::make()
                    ->title($result->successful ? 'Conexion correcta' : 'No se pudo conectar')
                    ->body($result->message)
                    ->status($result->successful ? 'success' : 'danger')
                    ->persistent()
                    ->send();
            });
    }

    private function forgetCredentialsAction(): Action
    {
        return Action::make('borrarCredenciales')
            ->label('Borrar configuracion')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Borrar las credenciales guardadas')
            // Se dice explicitamente lo que no hace: no pretende revocar nada en
            // el proveedor, solo olvidar lo que hay guardado aqui.
            ->modalDescription('Se desactivara el metodo y se olvidaran las llaves guardadas en esta tienda. No revoca ni cancela nada en el proveedor: eso se hace desde su propio panel.')
            ->modalSubmitActionLabel('Si, borrar las credenciales')
            ->visible(fn (): bool => auth()->user()?->can(AdminPermission::DeleteIntegrationCredentials->value) ?? false)
            ->schema([
                Select::make('provider')
                    ->label('Proveedor')
                    ->options($this->providerOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $provider = PaymentProvider::from($data['provider']);

                app(GatewaySettings::class)->forget($provider);

                Notification::make()
                    ->title('Configuracion borrada')
                    ->body($provider->label().' quedo desactivado y sin credenciales.')
                    ->success()
                    ->send();

                $this->mount();
            });
    }

    /**
     * @return array<string, string>
     */
    private function providerOptions(): array
    {
        return collect(PaymentProvider::cases())
            ->mapWithKeys(fn (PaymentProvider $p): array => [$p->value => $p->label()])
            ->all();
    }
}
