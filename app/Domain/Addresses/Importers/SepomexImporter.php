<?php

namespace App\Domain\Addresses\Importers;

use App\Models\PostalCode;
use Generator;
use RuntimeException;

/**
 * Lector del catalogo nacional de codigos postales de Correos de Mexico.
 *
 * El archivo que publica Correos de Mexico viene delimitado por barras
 * verticales, en codificacion Windows-1252 y con dos lineas de encabezado antes
 * de los datos. Se lee en flujo y se inserta por lotes porque el catalogo
 * nacional pasa de las 145 mil filas y cargarlo entero en memoria no cabe en el
 * limite habitual de PHP.
 */
class SepomexImporter
{
    /**
     * Columnas del catalogo oficial, en su orden.
     */
    private const COLUMNS = [
        'd_codigo',          // codigo postal
        'd_asenta',          // asentamiento
        'd_tipo_asenta',     // tipo de asentamiento
        'D_mnpio',           // municipio
        'd_estado',          // estado
        'd_ciudad',          // ciudad
        'd_CP',              // codigo postal de la administracion
        'c_estado',
        'c_oficina',
        'c_CP',
        'c_tipo_asenta',
        'c_mnpio',
        'id_asenta_cpcons',  // clave del asentamiento
        'd_zona',            // zona
        'c_cve_ciudad',
    ];

    private const BATCH_SIZE = 1000;

    /**
     * @param  callable(int):void|null  $onBatch  Recibe el total acumulado.
     */
    public function import(string $path, ?callable $onBatch = null): int
    {
        if (! is_readable($path)) {
            throw new RuntimeException("No se puede leer el archivo del catalogo: {$path}");
        }

        $imported = 0;
        $batch = [];

        foreach ($this->rows($path) as $row) {
            $batch[] = $row;

            if (count($batch) >= self::BATCH_SIZE) {
                $imported += $this->flush($batch);
                $batch = [];

                if ($onBatch !== null) {
                    $onBatch($imported);
                }
            }
        }

        if ($batch !== []) {
            $imported += $this->flush($batch);

            if ($onBatch !== null) {
                $onBatch($imported);
            }
        }

        return $imported;
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function flush(array $batch): int
    {
        // upsert y no insert: reimportar el catalogo actualiza los asentamientos
        // existentes en lugar de fallar por la clave unica, asi que el comando se
        // puede volver a correr cuando Correos publique una version nueva.
        PostalCode::upsert(
            $batch,
            uniqueBy: ['postcode', 'settlement', 'municipality'],
            update: ['settlement_type', 'state', 'city', 'zone', 'settlement_key', 'updated_at'],
        );

        return count($batch);
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    private function rows(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("No se pudo abrir el archivo del catalogo: {$path}");
        }

        try {
            $now = now();
            $lineNumber = 0;

            while (($line = fgets($handle)) !== false) {
                $lineNumber++;

                $line = $this->toUtf8($line);
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                $fields = explode('|', $line);

                // El archivo trae dos lineas de encabezado: el titulo del
                // catalogo y los nombres de columna. Se detectan por contenido y
                // no por numero de linea, porque Correos ha cambiado el
                // encabezado entre publicaciones.
                if (count($fields) < count(self::COLUMNS)) {
                    continue;
                }

                if ($lineNumber <= 3 && str_contains(mb_strtolower($line), 'd_codigo')) {
                    continue;
                }

                $row = array_combine(self::COLUMNS, array_slice($fields, 0, count(self::COLUMNS)));

                $postcode = $this->normalizePostcode($row['d_codigo']);

                // Sin un CP de cinco digitos la fila no sirve para nada.
                if ($postcode === null) {
                    continue;
                }

                $settlement = trim($row['d_asenta']);

                if ($settlement === '') {
                    continue;
                }

                yield [
                    'postcode' => $postcode,
                    'settlement' => $settlement,
                    'settlement_type' => $this->nullIfEmpty($row['d_tipo_asenta']),
                    'municipality' => trim($row['D_mnpio']),
                    'state' => trim($row['d_estado']),
                    'city' => $this->nullIfEmpty($row['d_ciudad']),
                    'zone' => $this->nullIfEmpty($row['d_zona']),
                    'settlement_key' => $this->nullIfEmpty($row['id_asenta_cpcons']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        } finally {
            fclose($handle);
        }
    }

    private function toUtf8(string $line): string
    {
        // El catalogo viene en Windows-1252. Sin convertirlo, los nombres con
        // acento llegan corrompidos a la base de datos.
        if (mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        return mb_convert_encoding($line, 'UTF-8', 'Windows-1252');
    }

    private function normalizePostcode(string $value): ?string
    {
        $digits = preg_replace('/\D/', '', trim($value)) ?? '';

        if ($digits === '') {
            return null;
        }

        // El catalogo a veces omite el cero inicial.
        $padded = str_pad($digits, 5, '0', STR_PAD_LEFT);

        return strlen($padded) === 5 ? $padded : null;
    }

    private function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
