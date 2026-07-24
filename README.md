# Chuta

Tienda en linea de suplementos alimenticios y deportivos. Monolito modular en
Laravel 12 con panel administrativo en Filament, escaparate en Blade y checkout
para invitados.

> Estado: en construccion. Consulta [Avance por etapas](#avance-por-etapas)
> para saber que hay implementado hoy.

## Requisitos

| Componente | Version usada | Nota |
|---|---|---|
| PHP | 8.2.12 | Extensiones necesarias: `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip` |
| MySQL | 8.0.41 | Tambien sirve MariaDB compatible con Hostinger |
| Composer | 2.x | |
| Node | 22 o superior | Solo para compilar assets; produccion no lo necesita |

Node y npm se usan durante el desarrollo o el despliegue para generar los
archivos de Vite. La tienda en produccion corre con PHP, MySQL y los assets ya
compilados.

## Instalacion local

```bash
composer install
```

```bash
npm install
```

Copia el archivo de entorno y genera la llave de la aplicacion:

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Crea la base de datos (la contraseña real va unicamente en tu `.env` local,
nunca en el repositorio):

```bash
mysql -u root -p -e "CREATE DATABASE chuta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Ajusta `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` en `.env`, y luego:

```bash
php artisan migrate
```

```bash
php artisan storage:link
```

```bash
npm run build
```

## Como se sirve el proyecto

La opcion preferida es apuntar el document root directamente a `public/`. Ahi el
punto de entrada es `public/index.php` y el `.htaccess` de la raiz no interviene.

Cuando eso no es posible y el servidor apunta a la carpeta del proyecto —por
ejemplo XAMPP sirviendo `http://localhost/chuta`— entran en juego dos archivos:

- `.htaccess` en la raiz manda los recursos estaticos a `public/` y cualquier
  otra ruta al front controller.
- `index.php` en la raiz arranca Laravel con las rutas corregidas.

Ese `index.php` de raiz existe por una razon concreta: reenviar directamente a
`public/index.php` desde la carpeta padre hace que `SCRIPT_NAME` incluya
`/public` mientras que la URL pedida no, y Laravel calcula mal la ruta base y
responde 404 en todas las rutas. Con el front controller en la raiz, las rutas,
los assets y las URLs firmadas se resuelven igual que en produccion.

El proyecto no lleva dominios ni rutas de carpeta escritos en el codigo: todo
sale de `APP_URL` y de los helpers `route()`, `url()`, `asset()` y
`Storage::url()`.

## Pruebas

Las pruebas usan una base de datos aparte, `chuta_testing`, para no tocar los
datos de desarrollo:

```bash
mysql -u root -p -e "CREATE DATABASE chuta_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

```bash
php vendor/bin/pest
```

Revision de estilo:

```bash
php vendor/bin/pint --test
```

`phpunit.xml` fija `APP_URL` en la raiz durante las pruebas. Si se dejara el
valor con subcarpeta, Laravel lo antepondria a cada peticion de prueba y
ninguna ruta coincidiria.

## Credenciales locales

Las cuentas iniciales se crean con los seeders y son exclusivamente para el
entorno local. En produccion debe exigirse el cambio de contraseña.

| Rol | Correo | Contraseña |
|---|---|---|
| Superadministrador | `superadmin@local.test` | `password` |
| Administrador | `admin@local.test` | `password` |

## Avance por etapas

- [x] Etapa 1 — Base del proyecto: Laravel 12, Filament 5, Tailwind 4, Alpine,
      Swiper, SweetAlert2, Spatie Permission, Sanctum, Pest, Pint, entorno,
      base de datos, servido en subcarpeta e integracion continua.
- [ ] Etapa 2 — Identidad, acceso, roles y permisos.
- [ ] Etapa 3 — Catalogo.
- [ ] Etapa 4 — Inventario y reservas.
- [ ] Etapa 5 — Temas, contenido y escaparate.
- [ ] Etapa 6 — Busqueda, carrito y promociones.
- [ ] Etapa 7 — SEPOMEX, envios y checkout.
- [ ] Etapa 8 — Pedidos y cuentas de cliente.
- [ ] Etapa 9 — Pagos.
- [ ] Etapa 10 — SMTP y Meta Ads.
- [ ] Etapa 11 — Reportes, auditoria y API.
- [ ] Etapa 12 — Pruebas y optimizacion.
- [ ] Etapa 13 — Documentacion y despliegue en Hostinger.

## Seguridad

- `.env` nunca se versiona. La integracion continua falla si aparece en el
  repositorio.
- Los secretos de las integraciones se guardan cifrados y se muestran
  enmascarados en el panel.
- El `.htaccess` de la raiz desactiva el listado de directorios y niega el
  acceso a `.env`, archivos de Composer, registros y demas archivos internos.
