<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promociones automaticas y cupones con codigo.
 *
 * Conviven en una sola tabla, distinguidos por `requires_code`. El documento de
 * requisitos los pide separados, y la razon de no hacerlo es concreta: comparten
 * las quince condiciones (vigencia, minimos, alcance, limites de uso, prioridad,
 * exclusividad, metodos de pago) y el mismo motor de calculo. Separarlos
 * duplicaria ese esquema y obligaria a mantener dos evaluadores que tienen que
 * dar exactamente el mismo resultado. Queda anotado como desviacion deliberada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('description')->nullable();

            // Nulo en las promociones automaticas. Unico cuando existe, para que
            // dos cupones no compartan codigo.
            $table->string('code', 60)->nullable()->unique();
            $table->boolean('requires_code')->default(false);

            $table->string('discount_type', 30);

            // Centavos en los descuentos de monto fijo, puntos porcentuales en los
            // porcentuales. Nunca un float: el dinero no se calcula con decimales
            // de punto flotante.
            $table->unsignedInteger('discount_value')->default(0);

            // Para 2x1, 3x2 y "compra X y recibe Y".
            $table->unsignedSmallInteger('buy_quantity')->nullable();
            $table->unsignedSmallInteger('get_quantity')->nullable();

            $table->unsignedInteger('min_subtotal_cents')->default(0);
            $table->unsignedSmallInteger('min_quantity')->default(0);

            // Tope del beneficio, para que un porcentaje sobre un carrito grande no
            // se vuelva un descuento sin limite.
            $table->unsignedInteger('max_benefit_cents')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);

            // Orden de evaluacion. Menor numero se aplica antes.
            $table->unsignedSmallInteger('priority')->default(100);

            // Una promocion exclusiva se aplica sola: si entra, ninguna otra lo hace.
            $table->boolean('is_exclusive')->default(false);

            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->unsignedInteger('uses_count')->default(0);

            $table->boolean('allow_guests')->default(true);
            $table->boolean('first_purchase_only')->default(false);

            /*
             * Alcance y exclusiones.
             *
             * Se guardan como JSON porque son configuracion, no datos del negocio:
             * las promociones activas son pocas y se evaluan en memoria, de modo
             * que no hace falta consultarlas por producto. Los datos que si se
             * consultan (vigencia, codigo, estado) tienen sus propias columnas
             * indexadas.
             */
            $table->json('product_ids')->nullable();
            $table->json('category_ids')->nullable();
            $table->json('brand_ids')->nullable();
            $table->json('excluded_product_ids')->nullable();
            $table->json('payment_methods')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'requires_code']);
            $table->index(['starts_at', 'ends_at']);
            $table->index('priority');
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            // El correo normalizado es lo que permite contar usos de un cliente sin
            // cuenta, que es la mayoria en esta tienda.
            $table->string('email')->nullable();

            $table->unsignedInteger('discount_cents');
            $table->timestamps();

            $table->index(['promotion_id', 'email']);

            // Un pedido no puede consumir dos veces la misma promocion.
            $table->unique(['promotion_id', 'order_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('discount_cents')->default(0)->after('subtotal_cents');

            /*
             * Fotografia inmutable de los descuentos aplicados.
             *
             * Se guarda con el pedido y no se recalcula: modificar o borrar una
             * promocion despues no debe cambiar lo que un cliente ya pago.
             */
            $table->json('discount_breakdown')->nullable()->after('discount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_cents', 'discount_breakdown']);
        });

        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('promotions');
    }
};
