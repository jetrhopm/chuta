<?php

namespace App\Filament\Pages;

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Meta\MetaPixelSettings;
use App\Domain\Meta\MetaPixelSettingsRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ManageMetaAdsSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $navigationLabel = 'Meta Ads';

    protected static string|UnitEnum|null $navigationGroup = 'Configuracion';

    protected string $view = 'filament.pages.manage-meta-ads-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(AdminPermission::ManageMetaAds->value) ?? false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Meta Ads';
    }

    public function mount(): void
    {
        $this->form->fill(app(MetaPixelSettingsRepository::class)->get()->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Meta Pixel')
                    ->description('Mide eventos para campanas de Facebook e Instagram. Si se desactiva o falta el Pixel ID, no se inyecta ningun script.')
                    ->schema([
                        Toggle::make('enabled')
                            ->label('Pixel activo')
                            ->live(),
                        TextInput::make('pixel_id')
                            ->label('Pixel ID')
                            ->helperText('Solo numeros. Lo encuentras en Events Manager.')
                            ->maxLength(32)
                            ->regex('/^\d+$/')
                            ->required(fn (callable $get): bool => (bool) $get('enabled')),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        app(MetaPixelSettingsRepository::class)->save(MetaPixelSettings::fromArray($this->form->getState()));

        Notification::make()
            ->title('Meta Pixel guardado')
            ->body('La tienda usara esta configuracion en las paginas publicas.')
            ->success()
            ->send();

        $this->mount();
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
