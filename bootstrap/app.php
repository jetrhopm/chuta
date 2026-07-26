<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Los avisos de los proveedores de pago no vienen de un formulario
        // nuestro, asi que no traen token CSRF. Lo que autentica el aviso es la
        // firma que verifica cada adaptador antes de mirar su contenido.
        $middleware->validateCsrfTokens(except: [
            'webhooks/pagos/*',
        ]);

        // El unico acceso de la aplicacion es el del panel. Sin esto, una ruta
        // protegida con el middleware de autenticacion buscaria una ruta llamada
        // "login" que no existe y lanzaria una excepcion en lugar de redirigir.
        $middleware->redirectGuestsTo(fn () => route('filament.admin.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
