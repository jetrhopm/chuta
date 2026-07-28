# Manual completo de instalacion, despliegue y operacion

Este documento explica el proyecto Chutamax paso a paso, pensado para alguien
que no sabe programar ni administrar servidores. Si un comando dice `USUARIO`,
`DOMINIO` o `BASE_DE_DATOS`, cambia esa palabra por el dato real.

Dominio de produccion actual:

```text
https://chutamax.gocentersuplementos.com.mx
```

Ruta usada en Hostinger:

```text
/home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
```

Repositorio GitHub:

```text
https://github.com/jetrhopm/chuta.git
```

---

## 1. Que es este sistema

Chutamax es una tienda en linea hecha con Laravel. Tiene dos partes principales:

- **Tienda publica:** lo que ve el cliente: portada, catalogo, producto, carrito,
  checkout, seguimiento del pedido y carga de comprobante.
- **Panel administrativo:** lo que usa el administrador: productos, categorias,
  pedidos, inventario, promociones, pagos, correo, envios, contenido de portada y
  Meta Pixel.

La tienda no es una pagina estatica. Usa base de datos, sesiones, formularios,
correo, archivos subidos, imagenes, rutas seguras y procesos de cola.

---

## 2. Tecnologias usadas

| Tecnologia | Donde esta | Para que sirve |
|---|---|---|
| PHP 8.2+ | Servidor Hostinger y local | Lenguaje principal que ejecuta Laravel. |
| Laravel 12 | `app/`, `routes/`, `config/` | Framework que maneja rutas, controladores, base de datos, sesiones, correos y seguridad. |
| Filament 5 | `app/Filament/` | Panel administrativo. Evita crear formularios y tablas desde cero. |
| MySQL o MariaDB | hPanel de Hostinger | Guarda productos, pedidos, usuarios, inventario, configuraciones y clientes. |
| Blade | `resources/views/` | Plantillas HTML de Laravel. Construye las pantallas publicas. |
| Tailwind CSS 4 | `resources/css/app.css` | Estilos visuales de la tienda. |
| Alpine.js | `resources/js/app.js` y vistas Blade | Interacciones ligeras del carrito y formularios. |
| Vite | `vite.config.js`, `public/build/` | Compila CSS y JavaScript para produccion. |
| Composer | `composer.json` | Instala dependencias PHP. |
| npm / Node | `package.json` | Instala y compila dependencias frontend. |
| Pest | `tests/` | Pruebas automaticas para comprobar que no se rompa el sistema. |
| Pint | `pint.json` | Revisa formato del codigo PHP. |
| Git | `.git/` | Control de versiones. Permite subir cambios a GitHub y bajarlos a Hostinger. |

---

## 3. Estructura de carpetas

```text
chuta/
+-- app/
+-- bootstrap/
+-- config/
+-- database/
+-- docs/
+-- public/
+-- resources/
+-- routes/
+-- storage/
+-- tests/
+-- vendor/
+-- node_modules/
+-- .env
+-- .env.example
+-- artisan
+-- composer.json
+-- package.json
+-- vite.config.js
```

### `app/`

Aqui vive la logica principal de la aplicacion.

Carpetas importantes:

- `app/Http/Controllers/`: recibe peticiones del navegador y decide que hacer.
- `app/Models/`: representa tablas de la base de datos.
- `app/Domain/`: reglas de negocio separadas por tema.
- `app/Filament/`: pantallas del panel administrativo.
- `app/Mail/`: correos que se envian.
- `app/Policies/`: permisos para decidir quien puede ver, crear, editar o borrar.
- `app/Providers/`: configuracion de arranque de Laravel.

### `app/Domain/`

Es la parte mas importante para entender la tienda por modulos.

| Carpeta | Que hace |
|---|---|
| `Access` | Roles, permisos y acceso al panel. |
| `Addresses` | Codigos postales y direccion de envio. |
| `Catalog` | Busqueda y reglas del catalogo. |
| `Customers` | Clientes, historial y direcciones. |
| `Inventory` | Movimiento de inventario y proteccion contra sobreventa. |
| `Media` | Descarga y guardado local de imagenes. |
| `Meta` | Configuracion de Meta Pixel. |
| `Notifications` | SMTP y avisos por correo. |
| `Payments` | Proveedores de pago, webhooks y comprobantes. |
| `Promotions` | Cupones, descuentos y reglas promocionales. |
| `Settings` | Configuraciones guardadas en base de datos. |
| `Shipping` | Calculo de envios. |
| `Storefront` | Contenido administrable de portada y tema visual. |

### `app/Filament/`

Aqui esta el panel administrativo.

Ejemplos:

- `app/Filament/Resources/Products/`: alta y edicion de productos.
- `app/Filament/Resources/Orders/`: gestion de pedidos.
- `app/Filament/Resources/Promotions/`: promociones y cupones.
- `app/Filament/Pages/ManageMailSettings.php`: configuracion SMTP.
- `app/Filament/Pages/ManagePaymentIntegrations.php`: configuracion de pagos.
- `app/Filament/Pages/ManageMetaAdsSettings.php`: Meta Pixel.

### `routes/`

Aqui se definen las direcciones del sitio.

Archivo principal:

```text
routes/web.php
```

Rutas publicas importantes:

| URL | Controlador | Que hace |
|---|---|---|
| `/` | `StorefrontController` | Portada de la tienda. |
| `/catalogo` | `CatalogController` | Catalogo con busqueda y filtros. |
| `/producto/{slug}` | `ProductController` | Ficha individual del producto. |
| `/checkout` | `CheckoutController` | Recibe el formulario de compra. |
| `/codigo-postal/{cp}` | `PostalCodeController` | Devuelve colonias, municipio y estado. |
| `/pedido/{code}` | `OrderTrackingController` | Seguimiento de pedido con liga firmada. |
| `/pedido/{code}/comprobante` | `PaymentReceiptController` | Sube comprobante de transferencia. |
| `/webhooks/pagos/{provider}` | `PaymentWebhookController` | Recibe avisos de proveedores de pago. |

### `resources/`

Aqui esta lo que el cliente ve.

- `resources/views/`: pantallas HTML con Blade.
- `resources/css/app.css`: estilos principales.
- `resources/js/app.js`: JavaScript principal.

Vistas publicas importantes:

| Archivo | Que muestra |
|---|---|
| `resources/views/storefront/home.blade.php` | Portada, carrito y checkout. |
| `resources/views/storefront/products/show.blade.php` | Ficha de producto. |
| `resources/views/storefront/catalog/index.blade.php` | Catalogo. |
| `resources/views/storefront/orders/show.blade.php` | Seguimiento del pedido. |
| `resources/views/components/storefront/layouts/simple.blade.php` | Layout para paginas sencillas. |
| `resources/views/components/analytics/meta-pixel.blade.php` | Script de Meta Pixel. |

### `public/`

Esta es la carpeta publica. Lo que esta aqui puede ser visto por el navegador.

Contenido importante:

- `public/index.php`: entrada principal de Laravel.
- `public/.htaccess`: reglas de Apache.
- `public/build/`: CSS y JavaScript compilados por Vite.
- `public/storage/`: enlace hacia imagenes publicas en `storage/app/public`.

Nunca pongas `.env`, respaldos de base de datos, contrasenas o archivos privados
dentro de `public/`.

### `database/`

Aqui esta la definicion de la base de datos.

- `database/migrations/`: crean o modifican tablas.
- `database/seeders/`: cargan datos iniciales.
- `database/factories/`: fabrican datos para pruebas.

Migraciones importantes:

- `create_catalog_tables`: marcas, categorias y productos.
- `create_order_tables`: pedidos y partidas.
- `create_inventory_movements_table`: historial de inventario.
- `create_settings_table`: configuraciones del panel.
- `create_postal_codes_table`: codigos postales SEPOMEX.
- `create_payment_tables`: pagos, eventos y comprobantes.
- `create_promotion_tables`: cupones y promociones.
- `add_advanced_catalog_fields`: variantes, etiquetas, imagenes multiples y SEO.
- `create_customers_and_addresses`: clientes y libreta de direcciones.

### `storage/`

Aqui Laravel guarda archivos generados.

- `storage/logs/laravel.log`: errores del sistema.
- `storage/app/public/`: imagenes y archivos publicos.
- `storage/app/private/`: archivos privados, como comprobantes.
- `storage/framework/`: cache interna de Laravel.

No se sube normalmente a GitHub porque puede contener archivos grandes o
privados.

### `vendor/`

Dependencias PHP instaladas por Composer. Se crea con:

```bash
composer install
```

No se edita a mano.

### `node_modules/`

Dependencias JavaScript instaladas por npm. Se crea con:

```bash
npm install
```

No se edita a mano.

### `.env`

Archivo privado de configuracion real.

Guarda:

- URL de la app.
- Credenciales de base de datos.
- Modo produccion o local.
- Configuracion inicial.

Este archivo nunca debe subirse a GitHub.

### `.env.example`

Plantilla sin secretos. Sirve para crear un `.env` nuevo.

---

## 4. Como viaja la informacion en una compra

Este es el flujo normal:

```text
Cliente abre la tienda
    |
Laravel consulta productos, banners, promociones y configuracion
    |
Blade genera el HTML
    |
El navegador muestra productos
    |
Cliente agrega productos al carrito
    |
Alpine.js guarda carrito temporal en el navegador
    |
Cliente llena datos de envio y pago
    |
Formulario envia POST /checkout
    |
CheckoutController valida todo en servidor
    |
Laravel recalcula precios, descuentos y envio
    |
Inventario descuenta existencias dentro de una transaccion
    |
Se crea Order y OrderItem en MySQL
    |
Se crea intento de pago segun metodo elegido
    |
Se envia correo al cliente y aviso interno
    |
Cliente ve liga de seguimiento del pedido
```

Que espera recibir `/checkout`:

- Productos e cantidades del carrito.
- Nombre del cliente.
- Correo.
- Telefono.
- Direccion de envio.
- Codigo postal.
- Metodo de pago.
- Cupon, si aplica.

Que entrega:

- Si todo esta correcto: crea pedido y redirige al seguimiento o proveedor de
  pago.
- Si falta algo: regresa con errores para que el cliente corrija.
- Si no hay inventario: no crea el pedido y muestra el problema.
- Si el cliente altera precios desde el navegador: se ignora, porque el servidor
  recalcula todo.

---

## 5. Como instalar en una computadora local

### 5.1 Requisitos

Necesitas:

- PHP 8.2 o superior.
- Composer 2.
- MySQL o MariaDB.
- Node 22 o superior.
- Git.

En Windows con XAMPP, revisa PHP:

```powershell
php -v
```

Revisa Composer:

```powershell
composer -V
```

Revisa Node:

```powershell
node -v
npm -v
```

Si algun comando dice que no existe, esa herramienta no esta instalada o no esta
en el PATH.

### 5.2 Bajar el proyecto

```powershell
cd C:\xampp\htdocs
git clone https://github.com/jetrhopm/chuta.git chuta
cd chuta
```

Si ya existe la carpeta:

```powershell
cd C:\xampp\htdocs\chuta
git pull origin main
```

### 5.3 Instalar dependencias

```powershell
composer install
npm install
```

### 5.4 Crear `.env`

```powershell
copy .env.example .env
php artisan key:generate
```

Edita `.env` y pon los datos de tu base local:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost/chuta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chuta
DB_USERNAME=root
DB_PASSWORD=
```

### 5.5 Crear base de datos local

Desde phpMyAdmin o consola crea una base llamada `chuta`.

Por consola:

```powershell
mysql -u root -p -e "CREATE DATABASE chuta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Si tu usuario root no tiene contrasena, cuando pida password solo presiona Enter.

### 5.6 Crear tablas y datos iniciales

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
```

### 5.7 Abrir localmente

Con XAMPP:

```text
http://localhost/chuta
```

Si prefieres el servidor de Laravel:

```powershell
php artisan serve
```

Luego abre:

```text
http://127.0.0.1:8000
```

---

## 6. Como trabajar con GitHub

### Ver que cambios tienes

```powershell
git status
```

Si ves archivos en rojo o verde, hay cambios locales.

### Guardar cambios en Git

```powershell
git add .
git commit -m "Describe el cambio"
```

### Subir a GitHub

```powershell
git push origin main
```

### Bajar lo ultimo de GitHub

```powershell
git pull origin main
```

Regla simple:

- En tu computadora haces cambios y `git push`.
- En Hostinger bajas esos cambios con `git fetch` o `git pull`.

---

## 7. Como subir a Hostinger desde GitHub

### 7.1 Entrar por SSH

Desde una terminal:

```bash
ssh -p 65002 u705161084@156.67.75.196
```

Cuando pida contrasena, pegala. La terminal no muestra los caracteres mientras
escribes; eso es normal.

### 7.2 Entrar a la carpeta del subdominio

```bash
cd /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
```

Confirma que estas en la carpeta correcta:

```bash
pwd
ls -la
```

Debe mostrar:

```text
/home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
```

### 7.3 Confirmar si ya es un repositorio Git

```bash
test -d .git && echo "si es repo git" || echo "no es repo git"
```

Si dice `si es repo git`, revisa el remoto:

```bash
git remote -v
```

Debe apuntar a:

```text
https://github.com/jetrhopm/chuta.git
```

Si dice `no es repo git`, la instalacion inicial se hace asi:

```bash
cd /home/u705161084/domains/chutamax.gocentersuplementos.com.mx
rm -rf public_html
git clone https://github.com/jetrhopm/chuta.git public_html
cd public_html
```

Usa `rm -rf public_html` solo si estas seguro de que no hay archivos que quieras
conservar dentro. Antes puedes revisar:

```bash
ls -la /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
```

### 7.4 Actualizar codigo desde GitHub

```bash
cd /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
git fetch origin main
git checkout -f origin/main
```

Esto deja Hostinger exactamente con lo que esta en GitHub.

### 7.5 Instalar dependencias PHP

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

Si `composer` no existe, prueba:

```bash
php composer.phar install --no-dev --prefer-dist --no-interaction --optimize-autoloader
```

### 7.6 Crear o revisar `.env`

Si no existe:

```bash
cp .env.example .env
php artisan key:generate
```

Editalo:

```bash
nano .env
```

Valores importantes:

```dotenv
APP_NAME=Chutamax
APP_ENV=production
APP_DEBUG=false
APP_URL=https://chutamax.gocentersuplementos.com.mx

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=TU_BASE_DE_DATOS
DB_USERNAME=TU_USUARIO_DE_BASE
DB_PASSWORD=TU_PASSWORD_DE_BASE

QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
```

Guarda en nano con:

- `Ctrl + O`
- Enter
- `Ctrl + X`

### 7.7 Crear tablas y permisos iniciales

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

Si es primera instalacion y quieres cargar todos los seeders:

```bash
php artisan db:seed --force
```

### 7.8 Crear enlace de imagenes

```bash
php artisan storage:link
```

Comprueba:

```bash
ls -la public/storage
```

Debe verse como enlace hacia `storage/app/public`.

### 7.9 Confirmar assets compilados

```bash
test -f public/build/manifest.json && echo "build instalado" || echo "falta public/build/manifest.json"
```

Si dice `build instalado`, esta bien.

Si dice que falta, y Hostinger tiene Node:

```bash
npm ci
npm run build
```

Si Hostinger no tiene Node, compila en tu computadora con:

```powershell
npm run build
```

Y sube la carpeta:

```text
public/build
```

por FTP o Administrador de archivos de Hostinger.

### 7.10 Limpiar y optimizar Laravel

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 7.11 Comando rapido para despliegues normales

Cuando ya esta instalado y solo quieres publicar lo ultimo:

```bash
cd /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
git fetch origin main
git checkout -f origin/main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 8. Como revisar que todo funciona

### 8.1 Revisar pagina publica

Abre:

```text
https://chutamax.gocentersuplementos.com.mx
```

Debes revisar:

- La pagina carga sin error 500.
- Se ven productos.
- Se ven imagenes.
- Abre un producto individual.
- El boton de agregar al carrito funciona.
- El checkout abre formulario de envio y pago.
- Se puede llenar una direccion.
- Se puede crear un pedido.

### 8.2 Revisar catalogo

Abre:

```text
https://chutamax.gocentersuplementos.com.mx/catalogo
```

Prueba:

- Buscar un producto.
- Filtrar por categoria.
- Abrir un producto.
- Confirmar que el precio sea correcto.
- Confirmar que productos sin stock no se vendan.

### 8.3 Revisar pedido

Haz una compra de prueba por transferencia.

Debe pasar esto:

1. La tienda crea un pedido.
2. Te manda a la pagina de seguimiento.
3. La pagina muestra instrucciones de pago.
4. Permite subir comprobante.
5. En el panel aparece el comprobante.
6. Al aprobarlo, el pedido queda aprobado.

### 8.4 Revisar panel administrativo

La URL normalmente es:

```text
https://chutamax.gocentersuplementos.com.mx/admin
```

Revisa:

- Puedes iniciar sesion.
- Puedes ver productos.
- Puedes ver pedidos.
- Puedes entrar a configuracion de envios.
- Puedes entrar a configuracion de correo.
- Puedes entrar a configuracion de pagos.
- Puedes entrar a `Configuracion > Meta Ads`.

### 8.5 Revisar que Meta Pixel funciona

En el panel:

1. Entra a `Configuracion > Meta Ads`.
2. Activa `Pixel activo`.
3. Escribe tu `Pixel ID`.
4. Guarda.

Luego abre la tienda publica.

Para revisar desde navegador:

1. Click derecho en la pagina.
2. Selecciona `Ver codigo fuente`.
3. Busca `connect.facebook.net/en_US/fbevents.js`.
4. Busca tu Pixel ID.

Eventos que debe medir:

| Evento | Cuando pasa |
|---|---|
| `PageView` | Cuando alguien abre una pagina. |
| `ViewContent` | Cuando alguien abre un producto. |
| `AddToCart` | Cuando agrega un producto al carrito. |
| `InitiateCheckout` | Cuando abre el checkout. |
| `Purchase` | Cuando un pedido queda con pago aprobado. |

Si desactivas Meta Pixel en el panel, el script no debe aparecer.

### 8.6 Revisar archivos sensibles

Abre estas URLs:

```text
https://chutamax.gocentersuplementos.com.mx/.env
https://chutamax.gocentersuplementos.com.mx/composer.json
https://chutamax.gocentersuplementos.com.mx/storage/logs/laravel.log
```

Lo correcto es que den error 403 o 404. Si se descargan, el servidor esta mal
apuntado y hay que corregirlo antes de vender.

### 8.7 Revisar logs si algo falla

En SSH:

```bash
cd /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
tail -n 100 storage/logs/laravel.log
```

Para ver el log en vivo:

```bash
tail -f storage/logs/laravel.log
```

Sal con:

```text
Ctrl + C
```

---

## 9. Comandos de diagnostico

### Saber donde estas

```bash
pwd
```

### Ver archivos de la carpeta

```bash
ls -la
```

### Confirmar PHP

```bash
php -v
```

### Confirmar Composer

```bash
composer -V
```

### Confirmar Node

```bash
node -v
npm -v
```

### Confirmar que existe build

```bash
test -f public/build/manifest.json && echo "build instalado" || echo "falta build"
```

### Confirmar que existe `.env`

```bash
test -f .env && echo ".env existe" || echo "falta .env"
```

### Confirmar conexion a base de datos

```bash
php artisan migrate:status
```

Si muestra lista de migraciones, Laravel pudo conectarse a la base.

### Confirmar rutas

```bash
php artisan route:list
```

### Confirmar cola

```bash
php artisan queue:work --stop-when-empty
```

Si procesa trabajos y termina, esta bien.

---

## 10. Tareas programadas en Hostinger

En hPanel busca `Cron Jobs`.

Agrega un cron cada minuto para correos y trabajos:

```bash
php /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html/artisan queue:work --stop-when-empty --max-time=55
```

Agrega otro cron cada minuto para tareas programadas:

```bash
php /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html/artisan schedule:run
```

Sin el cron de cola, los correos pueden quedar guardados pero no enviarse.

---

## 11. Que configurar desde el panel

### Productos

Ruta del panel:

```text
Catalogo > Productos
```

Sirve para:

- Crear productos.
- Cambiar precio.
- Cambiar stock.
- Subir imagen principal.
- Agregar galeria.
- Agregar etiquetas.
- Agregar variantes.
- Editar SEO.
- Activar o desactivar producto.

### Categorias

```text
Catalogo > Categorias
```

Agrupan productos y ayudan a filtrar.

### Inventario

```text
Inventario
```

No se debe cambiar stock directo en base de datos. El sistema registra entradas,
salidas y ajustes para tener historial.

### Pedidos

```text
Ventas > Pedidos
```

Sirve para:

- Ver pedidos.
- Cambiar estado.
- Revisar pago.
- Revisar productos comprados.
- Ver datos de envio.

### Comprobantes

```text
Ventas > Comprobantes
```

Sirve para aprobar o rechazar comprobantes de transferencia.

### Promociones

```text
Marketing > Promociones
```

Sirve para crear:

- Cupones.
- Descuentos por porcentaje.
- Descuentos fijos.
- 2x1.
- 3x2.
- Envio gratis.

### Contenido de portada

```text
Configuracion > Portada
```

Sirve para administrar:

- Carruseles.
- Bloques.
- Tema visual.
- Blog simple.

### Correo

```text
Configuracion > Correo
```

Configura SMTP y correo de prueba.

### Envios

```text
Configuracion > Envios
```

Configura tarifa, envio gratis y zonas sin cobertura.

### Pagos

```text
Configuracion > Pagos
```

Configura transferencia, Clip y proveedores disponibles.

### Meta Ads

```text
Configuracion > Meta Ads
```

Activa Meta Pixel y guarda Pixel ID.

---

## 12. Como identificar errores comunes

| Sintoma | Posible causa | Que revisar |
|---|---|---|
| Pantalla blanca o error 500 | Error PHP o falta extension | `storage/logs/laravel.log` |
| Todo da 404 | Document root o `.htaccess` mal | Que el dominio apunte al proyecto correcto |
| No carga CSS | Falta `public/build` | `test -f public/build/manifest.json` |
| No se ven imagenes | Falta `storage:link` o imagenes | `ls -la public/storage` |
| No conecta a base de datos | `.env` incorrecto | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` |
| Cambie `.env` y no aplica | Cache vieja | `php artisan optimize:clear && php artisan config:cache` |
| Correos no llegan | SMTP o cola | Panel correo y cron `queue:work` |
| Meta Pixel no aparece | No esta activo o falta Pixel ID | Panel `Configuracion > Meta Ads` |
| Un pedido no se marca pagado | Webhook/pago no confirmo | Eventos de pago y logs |

---

## 13. Mantenimiento normal

### Antes de tocar algo

Haz respaldo de:

- Base de datos.
- Carpeta `storage/app/public`.
- Archivo `.env`.

### Actualizar codigo

```bash
cd /home/u705161084/domains/chutamax.gocentersuplementos.com.mx/public_html
git fetch origin main
git checkout -f origin/main
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Limpiar caches

```bash
php artisan optimize:clear
```

### Volver a crear caches

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Poner modo mantenimiento

```bash
php artisan down
```

### Quitar modo mantenimiento

```bash
php artisan up
```

---

## 14. Que no debes hacer

- No subas `.env` a GitHub.
- No pongas contrasenas en archivos `.md`.
- No borres `storage/app/public` sin respaldo.
- No edites archivos dentro de `vendor/`.
- No edites archivos dentro de `node_modules/`.
- No cambies inventario directo en base de datos.
- No dejes `APP_DEBUG=true` en produccion.
- No despliegues en el dominio principal si el objetivo es el subdominio.
- No borres `public/build` si no puedes volver a compilar assets.

---

## 15. Checklist final despues de desplegar

Marca cada punto:

- [ ] `https://chutamax.gocentersuplementos.com.mx` abre.
- [ ] `https://chutamax.gocentersuplementos.com.mx/catalogo` abre.
- [ ] Las imagenes cargan.
- [ ] Se puede abrir un producto.
- [ ] Se puede agregar al carrito.
- [ ] Se puede llenar checkout.
- [ ] Se crea pedido.
- [ ] Llega correo de pedido.
- [ ] Abre pagina de seguimiento.
- [ ] Se puede subir comprobante.
- [ ] El panel abre en `/admin`.
- [ ] El administrador puede ver pedidos.
- [ ] El administrador puede aprobar comprobantes.
- [ ] El inventario baja al comprar.
- [ ] Meta Pixel aparece si esta activo.
- [ ] `.env` no es visible desde navegador.
- [ ] `composer.json` no es visible desde navegador.
- [ ] `storage/logs/laravel.log` no es visible desde navegador.
