<?php

use App\Domain\Addresses\Importers\SepomexImporter;
use App\Models\PostalCode;

beforeEach(function () {
    app(SepomexImporter::class)->import(base_path('tests/Fixtures/sepomex-muestra.txt'));
});

it('responde con todos los asentamientos del codigo postal', function () {
    $this->getJson('/codigo-postal/85000')
        ->assertOk()
        ->assertJsonPath('found', true)
        ->assertJsonPath('data.state', 'Sonora')
        ->assertJsonPath('data.municipality', 'Cajeme')
        ->assertJsonCount(3, 'data.settlements')
        ->assertJsonPath('data.settlements.0.name', 'Centro')
        ->assertJsonPath('data.settlements.0.type', 'Colonia');
});

it('invita a la captura manual cuando el codigo no existe', function () {
    $this->getJson('/codigo-postal/99999')
        ->assertNotFound()
        ->assertJsonPath('found', false)
        // El mensaje va dirigido al cliente: nada de tecnicismos ni de detalles
        // internos.
        ->assertJsonPath('message', 'No encontramos ese codigo postal; puedes escribir tu direccion manualmente.');
});

it('no expone detalles internos en la respuesta de error', function () {
    $respuesta = $this->getJson('/codigo-postal/99999')->getContent();

    expect($respuesta)->not->toContain('SQL')
        ->and($respuesta)->not->toContain('Exception')
        ->and($respuesta)->not->toContain('postal_codes');
});

it('rechaza un codigo postal que no sea numerico', function () {
    // La ruta exige digitos, asi que ni siquiera llega al controlador.
    $this->getJson('/codigo-postal/abcde')->assertNotFound();
});

it('sirve el segundo golpe desde la cache sin volver a consultar', function () {
    $this->getJson('/codigo-postal/85000')->assertOk();

    // Si la respuesta no viniera de la cache, borrar la tabla cambiaria el
    // resultado.
    PostalCode::query()->delete();

    $this->getJson('/codigo-postal/85000')
        ->assertOk()
        ->assertJsonCount(3, 'data.settlements');
});

it('limita la cantidad de consultas seguidas', function () {
    // Es una ruta publica que se llama a cada tecla; sin limite serviria para
    // recorrer el catalogo completo a fuerza de peticiones.
    foreach (range(1, 60) as $intento) {
        $this->getJson('/codigo-postal/85000')->assertOk();
    }

    $this->getJson('/codigo-postal/85000')->assertStatus(429);
});
