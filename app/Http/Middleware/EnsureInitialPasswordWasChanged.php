<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a cambiar la contrasena inicial antes de usar el panel.
 *
 * Las cuentas sembradas en produccion nacen marcadas con `must_change_password`.
 * Mientras la marca siga puesta, cualquier ruta del panel redirige al perfil.
 */
class EnsureInitialPasswordWasChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        $profileUrl = Filament::getCurrentOrDefaultPanel()?->getProfileUrl();

        // Sin pagina de perfil no hay a donde redirigir, y bloquear el panel
        // dejaria a la persona sin forma de arreglarlo.
        if ($profileUrl === null) {
            return $next($request);
        }

        // Dejar pasar la propia pagina de perfil y el cierre de sesion evita un
        // bucle de redirecciones.
        if ($request->fullUrlIs($profileUrl.'*') || $request->routeIs('filament.*.auth.logout')) {
            return $next($request);
        }

        return redirect()->to($profileUrl);
    }
}
