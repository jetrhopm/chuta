<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // El grupo separa configuraciones por modulo (envios, correo, pagos)
            // para poder leer un bloque completo con una sola consulta.
            $table->string('group', 64);
            $table->string('key', 128);

            // JSON para admitir numeros, banderas y listas sin una columna por
            // tipo. Los datos principales del negocio no viven aqui: esto es
            // solo configuracion.
            $table->json('value')->nullable();

            // Las credenciales se guardan cifradas. La bandera permite saber que
            // un valor viene cifrado sin tener que intentar descifrarlo.
            $table->boolean('is_encrypted')->default(false);

            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
