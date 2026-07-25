<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Umbral para avisar de existencias bajas. Cero desactiva el aviso.
            $table->unsignedInteger('stock_minimum')->default(0)->after('stock');

            // Hay productos que no se llevan por existencias. Cuando esto es
            // falso, el catalogo no bloquea la venta por falta de stock.
            $table->boolean('track_inventory')->default(true)->after('stock_minimum');
        });

        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete a proposito: el historial no puede quedar huerfano,
            // asi que un producto con movimientos no se puede borrar.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Quien lo hizo. Queda nulo cuando el movimiento lo genera una venta
            // de la tienda y no una persona del panel.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type');

            // Con signo: positivo entra, negativo sale. Guardar el signo evita
            // tener que interpretar el tipo para sumar el historial.
            $table->integer('quantity');

            // Existencias resultantes. Se guarda para poder auditar el historial
            // sin recalcularlo desde el principio.
            $table->unsignedInteger('stock_after');

            $table->string('reason')->nullable();
            $table->string('reference')->nullable();

            // Solo created_at: el historial es inmutable y no se edita, asi que
            // updated_at no tendria ningun significado.
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock_minimum', 'track_inventory']);
        });
    }
};
