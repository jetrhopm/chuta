<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postal_codes', function (Blueprint $table) {
            $table->id();

            // Cinco digitos exactos. char y no string: todos miden lo mismo y
            // asi la comparacion no arrastra longitudes variables.
            $table->char('postcode', 5);

            $table->string('settlement');
            $table->string('settlement_type', 100)->nullable();
            $table->string('municipality');
            $table->string('state');
            $table->string('city')->nullable();
            $table->string('zone', 50)->nullable();

            // Clave del asentamiento dentro del codigo postal, tal como viene en
            // el catalogo oficial. Sirve para reimportar sin duplicar.
            $table->string('settlement_key', 20)->nullable();

            $table->timestamps();

            // La consulta real es siempre "dame los asentamientos de este CP".
            $table->index('postcode');

            // Evita duplicados al reimportar el catalogo: un mismo CP puede
            // tener muchos asentamientos, pero no el mismo dos veces.
            $table->unique(['postcode', 'settlement', 'municipality'], 'postal_codes_unique_settlement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_codes');
    }
};
