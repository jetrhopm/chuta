<?php

/*
|--------------------------------------------------------------------------
| Front controller de respaldo para instalaciones en subcarpeta
|--------------------------------------------------------------------------
|
| Solo se usa cuando el document root apunta a la carpeta del proyecto en vez
| de apuntar a "public/" (por ejemplo XAMPP sirviendo http://localhost/chuta).
| En ese caso el .htaccess de la raiz manda aqui todas las rutas de la
| aplicacion.
|
| Existe porque reenviar directamente a "public/index.php" rompe la deteccion
| de la ruta base: SCRIPT_NAME incluiria "/public" mientras que la URL pedida
| no, y Laravel terminaria buscando "/chuta/" como si fuera una ruta. Al vivir
| este archivo en la raiz, SCRIPT_NAME y la URL coinciden y las rutas, los
| assets y las URLs firmadas se resuelven igual que en produccion.
|
| Cuando el document root si apunta a "public/", este archivo no interviene y
| el punto de entrada sigue siendo "public/index.php".
|
*/

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
