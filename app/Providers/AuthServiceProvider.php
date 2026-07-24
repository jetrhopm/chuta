<?php

namespace App\Providers;

use App\Listeners\RecordSuccessfulLogin;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Login::class, RecordSuccessfulLogin::class);

        // Ambas reglas van en `before` y no en `after`: un callback de `after`
        // solo puede rellenar un resultado nulo (`$result ??= $afterResult`),
        // nunca revocar un permiso ya concedido. Denegar desde ahi no habria
        // tenido ningun efecto.
        Gate::before(function (User $user, string $ability): ?bool {
            // Una cuenta desactivada no conserva ningun permiso, aunque su rol
            // siga teniendolos asignados. Se comprueba primero para que ni
            // siquiera el superadministrador se salte la restriccion.
            if (! $user->is_active) {
                return false;
            }

            // El superadministrador tiene acceso total, asi que se resuelve
            // antes de consultar permisos o Policies.
            if ($user->isSuperAdmin()) {
                return true;
            }

            // Consulta de permisos con nombre. Es la comprobacion que normalmente
            // registra Spatie por su cuenta; aqui se hace explicita para que el
            // orden respecto a la cuenta desactivada quede garantizado.
            // Ver `register_permission_check_method` en config/permission.php.
            //
            // Devolver null cuando no hay permiso es intencional: deja que
            // Policies y Gates definidos aparte sigan su curso, cosa que no
            // ocurriria devolviendo false.
            return $user->checkPermissionTo($ability) ?: null;
        });
    }
}
