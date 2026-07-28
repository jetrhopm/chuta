<?php

namespace App\Filament\Pages;

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Storefront\StorefrontContentRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class ManageStorefrontContent extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $navigationLabel = 'Portada';

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected string $view = 'filament.pages.manage-storefront-content';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can(AdminPermission::ManageContent->value)
            || auth()->user()?->can(AdminPermission::ManageThemes->value)
            || false;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Contenido de portada';
    }

    public function mount(): void
    {
        $content = app(StorefrontContentRepository::class);

        $this->form->fill([
            'theme' => $content->theme(),
            'banners' => $content->banners(),
            'content_blocks' => $content->contentBlocks(),
            'blog_posts' => $content->blogPosts(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Tema')
                    ->schema([
                        Select::make('theme')
                            ->label('Tema activo')
                            ->options(StorefrontContentRepository::THEMES)
                            ->required(),
                    ]),

                Section::make('Carrusel principal')
                    ->schema([
                        Repeater::make('banners')
                            ->label('Banners')
                            ->schema([
                                TextInput::make('image')
                                    ->label('URL o ruta de imagen')
                                    ->required(),
                                TextInput::make('alt')
                                    ->label('Texto alternativo')
                                    ->required()
                                    ->maxLength(160),
                                TextInput::make('url')
                                    ->label('Enlace')
                                    ->default('#productos')
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible(),
                    ]),

                Section::make('Bloques ordenables')
                    ->schema([
                        Repeater::make('content_blocks')
                            ->label('Bloques')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Titulo')
                                    ->required()
                                    ->maxLength(120),
                                Textarea::make('text')
                                    ->label('Texto')
                                    ->rows(3)
                                    ->required()
                                    ->maxLength(500),
                                TextInput::make('url')
                                    ->label('Enlace')
                                    ->default('#productos')
                                    ->maxLength(255),
                                Select::make('style')
                                    ->label('Estilo')
                                    ->options([
                                        'dark' => 'Negro',
                                        'brand' => 'Rojo',
                                        'light' => 'Claro',
                                    ])
                                    ->default('dark')
                                    ->required(),
                                TextInput::make('sort_order')
                                    ->label('Orden')
                                    ->integer()
                                    ->minValue(0)
                                    ->default(0),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible(),
                    ]),

                Section::make('Blog')
                    ->schema([
                        Repeater::make('blog_posts')
                            ->label('Entradas')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Titulo')
                                    ->required()
                                    ->maxLength(140),
                                Textarea::make('excerpt')
                                    ->label('Resumen')
                                    ->rows(3)
                                    ->required()
                                    ->maxLength(500),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->required()
                                    ->maxLength(255),
                                DatePicker::make('published_at')
                                    ->label('Fecha')
                                    ->native(false),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $content = app(StorefrontContentRepository::class);

        $content->saveTheme((string) ($data['theme'] ?? 'performance'));
        $content->saveBanners($data['banners'] ?? []);
        $content->saveContentBlocks($data['content_blocks'] ?? []);
        $content->saveBlogPosts($data['blog_posts'] ?? []);

        Notification::make()
            ->title('Portada actualizada')
            ->body('Los cambios ya se reflejan en la tienda.')
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
