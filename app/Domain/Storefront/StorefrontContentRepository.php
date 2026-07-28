<?php

namespace App\Domain\Storefront;

use App\Domain\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;

/**
 * Contenido de la portada que se administra en base de datos.
 *
 * Los banners viven aqui y no en un archivo de configuracion porque tienen que
 * poder cambiar en caliente: el comando de descarga de medios reescribe sus
 * rutas, y la Etapa 5 les anadira orden, vigencia y vista previa desde el panel.
 *
 * La configuracion solo aporta los valores de arranque para la primera
 * instalacion.
 */
class StorefrontContentRepository
{
    public const GROUP = 'storefront';

    public const THEMES = [
        'performance' => 'Performance',
        'electric' => 'Electric',
        'premium' => 'Premium',
        'fresh' => 'Fresh',
    ];

    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @return array<int, array{image: string, alt: string, url: string}>
     */
    public function banners(): array
    {
        $banners = $this->settings->get(self::GROUP, 'banners');

        if (! is_array($banners)) {
            return [];
        }

        return array_values(array_filter(
            $banners,
            fn ($banner): bool => is_array($banner) && ($banner['image'] ?? '') !== '',
        ));
    }

    /**
     * Banners listos para pintar, con la direccion de la imagen ya resuelta.
     *
     * Mientras un banner no se haya descargado sigue apuntando a su origen; una
     * vez descargado guarda una ruta relativa del disco publico.
     *
     * @return array<int, array{image: string, alt: string, url: string}>
     */
    public function displayBanners(): array
    {
        return array_map(function (array $banner): array {
            $image = (string) $banner['image'];

            return [
                'image' => str_starts_with($image, 'http')
                    ? $image
                    : Storage::disk('public')->url($image),
                'alt' => (string) ($banner['alt'] ?? ''),
                'url' => (string) ($banner['url'] ?? '#'),
            ];
        }, $this->banners());
    }

    /**
     * @return array<int, array{title: string, text: string, url: string, style: string, sort_order: int}>
     */
    public function contentBlocks(): array
    {
        $blocks = $this->settings->get(self::GROUP, 'content_blocks');

        if (! is_array($blocks)) {
            return [];
        }

        return collect($blocks)
            ->filter(fn ($block): bool => is_array($block) && filled($block['title'] ?? null))
            ->sortBy(fn (array $block): int => (int) ($block['sort_order'] ?? 0))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{title: string, excerpt: string, url: string, published_at: string}>
     */
    public function blogPosts(): array
    {
        $posts = $this->settings->get(self::GROUP, 'blog_posts');

        if (! is_array($posts)) {
            return [];
        }

        return collect($posts)
            ->filter(fn ($post): bool => is_array($post) && filled($post['title'] ?? null))
            ->sortByDesc(fn (array $post): string => (string) ($post['published_at'] ?? ''))
            ->values()
            ->all();
    }

    public function theme(): string
    {
        $theme = $this->settings->get(self::GROUP, 'theme', 'performance');

        return array_key_exists($theme, self::THEMES) ? $theme : 'performance';
    }

    /**
     * @param  array<int, array{image: string, alt: string, url: string}>  $banners
     */
    public function saveBanners(array $banners): void
    {
        $this->settings->set(self::GROUP, 'banners', array_values($banners));
    }

    public function saveTheme(string $theme): void
    {
        $this->settings->set(self::GROUP, 'theme', array_key_exists($theme, self::THEMES) ? $theme : 'performance');
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function saveContentBlocks(array $blocks): void
    {
        $this->settings->set(self::GROUP, 'content_blocks', array_values($blocks));
    }

    /**
     * @param  array<int, array<string, mixed>>  $posts
     */
    public function saveBlogPosts(array $posts): void
    {
        $this->settings->set(self::GROUP, 'blog_posts', array_values($posts));
    }

    /**
     * Siembra los banners iniciales sin pisar los que ya esten guardados.
     */
    public function seedDefaults(): void
    {
        $this->settings->seedMissing(self::GROUP, [
            'banners' => array_values(config('storefront.banners', [])),
            'theme' => 'performance',
            'content_blocks' => [],
            'blog_posts' => [],
        ]);
    }
}
