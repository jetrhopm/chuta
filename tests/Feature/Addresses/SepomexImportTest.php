<?php

use App\Domain\Addresses\Importers\SepomexImporter;
use App\Domain\Addresses\PostalCodeLookup;
use App\Models\PostalCode;

function rutaMuestra(): string
{
    return base_path('tests/Fixtures/sepomex-muestra.txt');
}

it('importa el catalogo de muestra saltandose encabezados y filas invalidas', function () {
    $importados = app(SepomexImporter::class)->import(rutaMuestra());

    // Del archivo se descartan: las dos lineas de encabezado, la fila sin
    // asentamiento y la fila con menos columnas de las esperadas. Quedan las
    // siete filas utiles.
    expect($importados)->toBe(7)
        ->and(PostalCode::count())->toBe(7);
});

it('convierte los acentos del catalogo correctamente', function () {
    app(SepomexImporter::class)->import(rutaMuestra());

    $row = PostalCode::where('postcode', '06700')->firstOrFail();

    expect($row->municipality)->toBe('Cuauhtémoc')
        ->and($row->state)->toBe('Ciudad de México');
});

it('convierte texto en Windows-1252 sin corromper los acentos', function () {
    $linea = mb_convert_encoding(
        '85100|Bácum|Colonia|Bácum|Sonora|Bácum|85100|26|85100||09|007|0001|Urbano|01',
        'Windows-1252',
        'UTF-8',
    );

    $archivo = tempnam(sys_get_temp_dir(), 'sepomex');
    file_put_contents($archivo, $linea.PHP_EOL);

    app(SepomexImporter::class)->import($archivo);
    unlink($archivo);

    // Sin la conversion de codificacion, los nombres con acento llegarian
    // corrompidos a la base de datos.
    expect(PostalCode::where('postcode', '85100')->value('settlement'))->toBe('Bácum');
});

it('rellena el cero inicial que a veces omite el catalogo', function () {
    app(SepomexImporter::class)->import(rutaMuestra());

    // En el archivo esa fila viene como "6760".
    expect(PostalCode::where('settlement', 'Condesa')->value('postcode'))->toBe('06760');
});

it('no duplica al reimportar el mismo catalogo', function () {
    app(SepomexImporter::class)->import(rutaMuestra());
    app(SepomexImporter::class)->import(rutaMuestra());
    app(SepomexImporter::class)->import(rutaMuestra());

    expect(PostalCode::count())->toBe(7);
});

it('falla con un mensaje claro si el archivo no existe', function () {
    expect(fn () => app(SepomexImporter::class)->import(base_path('tests/Fixtures/no-existe.txt')))
        ->toThrow(RuntimeException::class);
});

it('devuelve todos los asentamientos de un codigo postal', function () {
    app(SepomexImporter::class)->import(rutaMuestra());

    $result = app(PostalCodeLookup::class)->find('85000');

    // Todos, no una seleccion: recortar la lista dejaria al cliente sin poder
    // elegir su colonia.
    expect($result['settlements'])->toHaveCount(3)
        ->and(array_column($result['settlements'], 'name'))->toBe(['Centro', 'Cortinas', 'Municipio Libre'])
        ->and($result['state'])->toBe('Sonora')
        ->and($result['municipality'])->toBe('Cajeme')
        ->and($result['city'])->toBe('Ciudad Obregón');
});

it('no encuentra nada con un codigo que no tiene cinco digitos', function () {
    app(SepomexImporter::class)->import(rutaMuestra());

    $lookup = app(PostalCodeLookup::class);

    expect($lookup->find('850'))->toBeNull()
        ->and($lookup->find('850000'))->toBeNull()
        ->and($lookup->find('abcde'))->toBeNull();
});

it('el comando importa y reporta el resultado', function () {
    $this->artisan('sepomex:import', ['path' => rutaMuestra()])
        ->assertSuccessful();

    expect(PostalCode::count())->toBe(7);
});

it('el comando falla sin tumbar la aplicacion si el archivo no existe', function () {
    $this->artisan('sepomex:import', ['path' => base_path('tests/Fixtures/no-existe.txt')])
        ->assertFailed();
});

it('vacia la tabla con la bandera fresh', function () {
    app(SepomexImporter::class)->import(rutaMuestra());

    PostalCode::create([
        'postcode' => '99999',
        'settlement' => 'Colonia inventada',
        'municipality' => 'Municipio',
        'state' => 'Estado',
    ]);

    expect(PostalCode::count())->toBe(8);

    $this->artisan('sepomex:import', ['path' => rutaMuestra(), '--fresh' => true])
        ->assertSuccessful();

    // La colonia inventada desaparece: --fresh vacia la tabla antes de importar.
    expect(PostalCode::count())->toBe(7)
        ->and(PostalCode::where('postcode', '99999')->exists())->toBeFalse();
});
