<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: un intento de pago es un registro contable y no
            // puede quedar huerfano.
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            $table->string('provider', 40);
            $table->string('status', 30)->default('pending');

            $table->unsignedInteger('amount_cents');
            $table->char('currency', 3)->default('MXN');

            // Identificador del pago en el proveedor. Es la unica forma de
            // volver a preguntarle por el estado.
            $table->string('external_id')->nullable();
            $table->text('checkout_url')->nullable();

            // Idempotencia: un doble clic o un reintento de la red no deben
            // generar dos cobros del mismo pedido.
            $table->string('idempotency_key', 64)->unique();

            // Queda registrado en que ambiente se hizo, para no confundir un
            // cobro de pruebas con uno real al revisar el historial.
            $table->boolean('sandbox')->default(true);

            // Fotografia de lo enviado y lo recibido, ya sin secretos. Sirve para
            // aclarar una diferencia con el proveedor sin tener que reproducirla.
            $table->json('request_snapshot')->nullable();
            $table->json('response_snapshot')->nullable();

            $table->string('failure_reason')->nullable();
            $table->unsignedInteger('refunded_cents')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index(['provider', 'external_id']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider', 40);

            // Identificador del evento en el proveedor. La clave unica junto al
            // proveedor es lo que hace idempotente el webhook: si llega dos veces
            // el mismo aviso, el segundo no se vuelve a procesar.
            $table->string('event_id')->nullable();

            $table->string('event_type')->nullable();
            $table->string('external_id')->nullable();

            // Se guarda tambien cuando la firma no cuadra: un webhook falso es
            // justo lo que interesa poder revisar despues.
            $table->boolean('signature_valid')->default(false);

            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->index('external_id');
        });

        Schema::create('payment_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');

            $table->string('status', 20)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_attempts');
    }
};
