<?php

it('sirve la pagina de inicio', function () {
    $this->get('/')->assertOk();
});

it('muestra el catalogo inicial en la tienda', function () {
    $this->seed();

    $this->get('/')
        ->assertOk()
        ->assertSee('Chutamax')
        ->assertSee('BSN No-Xplode Pre Entreno 60 servicios')
        ->assertSee('Optimum Nutrition Proteina Gold Standard 100% Whey 5 libras');
});

it('protege el panel administrativo de visitantes anonimos', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
