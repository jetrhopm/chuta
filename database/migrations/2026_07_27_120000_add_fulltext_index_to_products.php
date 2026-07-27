<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indice de texto completo para buscar en el catalogo.
 *
 * Se usa el indice nativo de MySQL en lugar de un servicio externo como
 * Elasticsearch: el documento de requisitos pide que la busqueda funcione en
 * Hostinger sin depender de nada mas, y con este catalogo un indice FULLTEXT da
 * resultados relevantes de sobra.
 *
 * El indice cubre el nombre y las descripciones. El SKU queda fuera a proposito:
 * son cadenas como "CHUTAMAX-3044" que el analizador de palabras parte en trozos
 * inutiles, asi que se busca por coincidencia exacta aparte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'short_description', 'description'], 'products_fulltext');
        });
    }

    public function down(): void
    {
        if (! $this->isMySql()) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText('products_fulltext');
        });
    }

    /**
     * El indice de texto completo es especifico de MySQL y MariaDB.
     *
     * Se comprueba para que las migraciones sigan corriendo en otro motor, donde
     * la busqueda cae al plan alterno por coincidencia parcial.
     */
    private function isMySql(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], strict: true);
    }
};
