<?php

it('sirve la pagina de inicio', function () {
    $this->get('/')->assertOk();
});

it('muestra el catalogo inicial en la tienda', function () {
    $this->seed();

    $this->get('/')
        ->assertOk()
        ->assertSee('Chutamax')
        ->assertSee('Whey Protein Vainilla 5 lb')
        ->assertSee('Creatina Monohidratada 300 g');
});

it('protege el panel administrativo de visitantes anonimos', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
