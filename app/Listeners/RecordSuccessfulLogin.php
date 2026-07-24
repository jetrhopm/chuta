<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

/**
 * Deja constancia del ultimo acceso de cada administrador.
 *
 * Se guarda solo lo necesario para investigar un acceso indebido: fecha, IP y
 * agente de usuario. Nada mas.
 */
class RecordSuccessfulLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        // Sin timestamps ni eventos: es un registro de auditoria, no una
        // edicion del perfil, y no deberia disparar la logica del modelo.
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $this->request->ip(),
            'last_login_user_agent' => mb_substr((string) $this->request->userAgent(), 0, 512),
        ])->saveQuietly();
    }
}
