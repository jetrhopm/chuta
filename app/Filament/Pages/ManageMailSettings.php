<?php

namespace App\Filament\Pages;

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Notifications\ConfigureMailer;
use App\Domain\Notifications\MailSettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * Configuracion del envio de correo.
 *
 * Una vez capturado aqui, este SMTP manda sobre el del archivo de entorno, que
 * queda solo como respaldo de arranque. La contrasena se guarda cifrada y se
 * muestra enmascarada.
 */
class ManageMailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Correo';

    protected static string|UnitEnum|null $navigationGroup = 'Configuracion';

    protected string $view = 'filament.pages.manage-mail-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(AdminPermission::ManageMailSettings->value) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Configuracion de correo';
    }

    public function mount(): void
    {
        $this->form->fill(app(MailSettingsRepository::class)->forDisplay());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Servidor de salida')
                    ->description('Mientras esto no este activo y completo, los correos de la tienda no salen. El pedido se guarda igual.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Envio de correo activo'),
                        TextInput::make('host')
                            ->label('Servidor')
                            ->placeholder('smtp.midominio.com')
                            ->maxLength(160),
                        TextInput::make('port')
                            ->label('Puerto')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(65535)
                            ->default(587),
                        Select::make('encryption')
                            ->label('Cifrado')
                            ->options([
                                'tls' => 'TLS',
                                'ssl' => 'SSL',
                                '' => 'Sin cifrado',
                            ])
                            ->default('tls'),
                        TextInput::make('username')
                            ->label('Usuario')
                            ->autocomplete(false)
                            ->maxLength(160),
                        TextInput::make('password')
                            ->label('Contrasena')
                            ->helperText('Dejala vacia para conservar la que ya esta guardada.')
                            ->password()
                            ->revealable()
                            ->autocomplete(false),
                        TextInput::make('timeout')
                            ->label('Tiempo de espera')
                            ->helperText('Segundos. Un valor bajo evita que un servidor lento retrase la cola.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(120)
                            ->default(15),
                    ])
                    ->columns(2),

                Section::make('Remitente')
                    ->schema([
                        TextInput::make('from_address')
                            ->label('Correo remitente')
                            ->helperText('Debe ser una direccion que tu servidor tenga permitido usar.')
                            ->email()
                            ->maxLength(160),
                        TextInput::make('from_name')
                            ->label('Nombre remitente')
                            ->maxLength(120),
                        TextInput::make('admin_notification_address')
                            ->label('Avisar cada venta a')
                            ->helperText('Opcional. Dejalo vacio para no recibir avisos internos.')
                            ->email()
                            ->maxLength(160)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Una contrasena que sigue enmascarada significa "no la cambies".
        if (is_string($data['password'] ?? null) && str_starts_with($data['password'], '****')) {
            $data['password'] = '';
        }

        app(MailSettingsRepository::class)->save($data);

        Notification::make()
            ->title('Configuracion de correo guardada')
            ->success()
            ->send();

        $this->mount();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [$this->sendTestAction()];
    }

    private function sendTestAction(): Action
    {
        return Action::make('correoDePrueba')
            ->label('Enviar correo de prueba')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->schema([
                TextInput::make('to')
                    ->label('Enviar a')
                    ->email()
                    ->required()
                    ->default(fn (): ?string => auth()->user()?->email),
            ])
            ->action(function (array $data): void {
                // Se guarda antes de probar para que la prueba use lo que hay en
                // pantalla y no una configuracion anterior.
                $this->save();

                if (! app(ConfigureMailer::class)->apply()) {
                    Notification::make()
                        ->title('Configuracion incompleta')
                        ->body('Falta activar el envio, el servidor o el correo remitente.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    // Sin cola: la prueba tiene que decir ahora mismo si funciona.
                    Mail::raw(
                        'Si estas leyendo esto, la configuracion de correo de tu tienda funciona.',
                        fn ($message) => $message->to($data['to'])->subject('Correo de prueba de tu tienda'),
                    );
                } catch (Throwable $exception) {
                    Notification::make()
                        ->title('No se pudo enviar')
                        // El mensaje del servidor de correo si es util aqui: lo lee
                        // un administrador para arreglar la configuracion.
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Correo enviado')
                    ->body('Revisa la bandeja de '.$data['to'].'. Si no llega, revisa tambien la carpeta de no deseados.')
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
