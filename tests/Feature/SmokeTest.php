<?php

it('sirve la pagina de inicio', function () {
    $this->get('/')->assertOk();
});

it('protege el panel administrativo de visitantes anonimos', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});
