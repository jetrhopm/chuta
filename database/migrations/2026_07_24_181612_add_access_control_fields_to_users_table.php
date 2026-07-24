<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Una cuenta desactivada conserva su historial y sus pedidos; por eso
            // se marca en lugar de borrarse.
            $table->boolean('is_active')->default(true)->after('password');

            // El documento de requisitos exige cambiar las contrasenas iniciales
            // en produccion. Esta bandera permite forzarlo sin tocar codigo.
            $table->boolean('must_change_password')->default(false)->after('is_active');

            // Solo se guarda lo necesario para investigar un acceso indebido.
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->string('last_login_user_agent', 512)->nullable()->after('last_login_ip');

            // Segundo factor para administradores. Se guarda cifrado y queda
            // inactivo mientras no exista fecha de confirmacion.
            $table->text('two_factor_secret')->nullable()->after('last_login_user_agent');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active']);

            $table->dropColumn([
                'is_active',
                'must_change_password',
                'last_login_at',
                'last_login_ip',
                'last_login_user_agent',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
            ]);
        });
    }
};
