<?php

use App\Domain\Access\Enums\AdminPermission;
use App\Domain\Access\Enums\AdminRole;
use App\Domain\Storefront\StorefrontContentRepository;
use App\Filament\Pages\ManageStorefrontContent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('guarda tema banners bloques y blog de la portada', function () {
    $this->actingAs(User::factory()->withRole(AdminRole::Admin)->create());

    Livewire::test(ManageStorefrontContent::class)
        ->fillForm([
            'theme' => 'premium',
            'banners' => [
                ['image' => 'banners/oferta.jpg', 'alt' => 'Oferta principal', 'url' => '#productos'],
            ],
            'content_blocks' => [
                ['title' => 'Envio gratis', 'text' => 'Desde $800 MXN.', 'url' => '#productos', 'style' => 'brand', 'sort_order' => 1],
            ],
            'blog_posts' => [
                ['title' => 'Como elegir creatina', 'excerpt' => 'Guia rapida para comprar mejor.', 'url' => '/blog/creatina', 'published_at' => '2026-07-28'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $content = app(StorefrontContentRepository::class);

    expect($content->theme())->toBe('premium')
        ->and($content->banners())->toHaveCount(1)
        ->and($content->contentBlocks()[0]['title'])->toBe('Envio gratis')
        ->and($content->blogPosts()[0]['title'])->toBe('Como elegir creatina');
});

it('niega la pantalla a quien no puede gestionar contenido ni temas', function () {
    $admin = User::factory()->withRole(AdminRole::Admin)->create();

    Role::where('name', AdminRole::Admin->value)
        ->firstOrFail()
        ->revokePermissionTo([
            AdminPermission::ManageContent->value,
            AdminPermission::ManageThemes->value,
        ]);

    $this->actingAs($admin->fresh());

    expect(ManageStorefrontContent::canAccess())->toBeFalse();

    $this->get(ManageStorefrontContent::getUrl())->assertForbidden();
});

it('aplica el tema y muestra bloques administrables en la portada', function () {
    $content = app(StorefrontContentRepository::class);
    $content->saveTheme('fresh');
    $content->saveContentBlocks([
        ['title' => 'Asesoria real', 'text' => 'Compras con datos claros.', 'url' => '#productos', 'style' => 'light', 'sort_order' => 1],
    ]);
    $content->saveBlogPosts([
        ['title' => 'Proteina o creatina', 'excerpt' => 'Cuando conviene cada una.', 'url' => '/blog/proteina-creatina', 'published_at' => '2026-07-28'],
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="fresh"', escape: false)
        ->assertSee('Asesoria real')
        ->assertSee('Proteina o creatina');
});
