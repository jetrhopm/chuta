# Chuta

Tienda en linea de suplementos alimenticios y deportivos. Monolito modular en
Laravel 12 con panel administrativo en Filament, escaparate en Blade y checkout
para invitados.

> Estado: en construccion. Consulta [Avance por etapas](#avance-por-etapas)
> para saber que hay implementado hoy.

## Puesta en produccion

Para desplegar en Hostinger sigue [docs/despliegue-produccion.md](docs/despliegue-produccion.md),
que cubre en orden la base de datos, el entorno, las migraciones, los medios, el
cron de la cola, el correo, los pagos y la comprobacion final.

## Requisitos

| Componente | Version usada | Nota |
|---|---|---|
| PHP | 8.2.12 | Extensiones necesarias: `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip` |

`intl` no es opcional: el panel formatea importes con `Number::currency()`, que
sin esa extension lanza una excepcion y deja las pantallas de productos y
pedidos en error 500. Si acabas de habilitarla en `php.ini`, **reinicia Apache**:
el modulo de PHP lee su configuracion al arrancar, asi que un servidor que ya
estaba corriendo sigue sin la extension. Las pruebas usan PHP de linea de
comandos y no avisan de esto, porque ahi la configuracion se lee en cada
ejecucion.
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

Dos detalles del `.htaccess` de la raiz que conviene no deshacer:

- **Las reglas terminan en `[END]`, no en `[L]`.** Apache reevalua el
  `.htaccess` despues de cada reescritura, y en esa segunda pasada
  `REQUEST_URI` ya apunta a `index.php`. La regla de recursos estaticos
  encontraria entonces `public/index.php` en disco y desviaria la peticion ahi,
  reintroduciendo justo el problema de la ruta base. `[END]` cierra el proceso.
- **Los recursos estaticos se deciden comprobando si el archivo existe, no por
  su extension.** Livewire publica su JavaScript desde una ruta de Laravel y no
  como archivo en disco, asi que una regla que capture todo lo terminado en
  `.js` deja `livewire.js` en 404 y el panel entero sin funcionar. La ruta
  absoluta para esa comprobacion se construye a partir de `DOCUMENT_ROOT`, sin
  escribir el nombre de la carpeta del proyecto.

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

## Inventario

Las existencias solo se modifican a traves de
`App\Domain\Inventory\Actions\RecordInventoryMovement`. Esa accion relee el
producto con la fila bloqueada, valida que el resultado no quede en negativo,
actualiza la columna y escribe el movimiento en el historial, todo en una
transaccion. Nadie deberia escribir `stock` a mano: es lo que garantiza que la
columna y el historial no puedan contradecirse.

El bloqueo de fila es lo que evita la sobreventa. Sin el, dos compras
simultaneas leerian las mismas existencias y venderian dos veces la ultima
pieza; con el, la segunda espera a que la primera termine. Hay una prueba que
falla si alguien quita el `lockForUpdate`.

El descuento ocurre dentro de la misma transaccion que crea el pedido, asi que
un producto agotado a media compra deshace el pedido completo en lugar de
dejarlo guardado sin existencias.

El historial es inmutable: el modelo `InventoryMovement` lanza una excepcion al
intentar modificarlo o borrarlo. Una correccion se hace registrando un ajuste en
sentido contrario, no reescribiendo el pasado.

Cancelar un pedido repone lo que ese pedido habia descontado, y solo una vez:
cancelar dos veces no puede inflar las existencias con piezas que no existen.

**Reservas durante el pago.** El documento de requisitos las pide, y todavia no
estan. Hoy el pedido descuenta en firme al confirmarse porque no existe una
pasarela de pago y, por tanto, no hay ventana de pago que reservar. Implementar
el ciclo de reserva ahora dejaria codigo que nada ejercita. Se hara junto con los
pagos, cuando haya un pago pendiente real que pueda expirar.

## Identidad visual y temas

El escaparate reproduce la identidad de la tienda: negro, blanco, grises y el
rojo de marca `#BF081F`, con **Bebas Neue** para titulos y nombres de producto,
**Anton** para los encabezados de seccion y **Roboto** para el texto corrido.

Las tres tipografias se instalan por npm y las empaqueta Vite. No se piden a un
servidor externo: en produccion la tienda debe funcionar solo con los archivos
compilados.

Los colores viven como design tokens en variables CSS dentro de
`resources/css/app.css`, y los componentes leen esas variables en lugar de
colores escritos a mano, para que cambiar de tema no obligue a tocar plantillas.
Hay cuatro temas definidos —`performance` (el activo, la identidad actual),
`electric`, `premium` y `fresh`— y se cambian poniendo un atributo
`data-theme` en el elemento raiz. La pantalla del panel para elegirlo,
personalizar tokens y exportar o importar la configuracion es parte de la Etapa 5.

### Imagenes: todo se sirve desde esta aplicacion

La tienda no depende de ningun sitio externo para sus imagenes. Los productos y
los banners se sirven desde `storage/app/public`, a traves del enlace simbolico
que crea `php artisan storage:link`.

Si tras importar un catalogo quedan imagenes apuntando a otro dominio, este
comando las trae a este servidor y reescribe las rutas en la base de datos:

```bash
php artisan media:localize
```

Para ver antes que haria, sin escribir nada:

```bash
php artisan media:localize --dry-run
```

En un servidor compartido conviene hacerlo por tandas:

```bash
php artisan media:localize --limit=200
```

El comando es idempotente y se puede interrumpir: lo ya descargado se salta, asi
que volver a ejecutarlo continua donde se quedo. Distingue dos tipos de fallo,
porque no se arreglan igual:

- **La imagen ya no existe en el origen** (respuesta 4xx, o el archivo no es una
  imagen). No va a mejorar reintentando, asi que se olvida la direccion y el
  producto muestra el marcador de posicion. La tienda deja de depender de ese
  sitio incluso para las fotos que se perdieron.
- **Fallo pasajero** (5xx, timeout, conexion caida). Se conserva la direccion
  para reintentarla, y el comando termina con error para que un despliegue
  automatizado se entere.

El archivo se valida por su contenido y no por la extension de la URL ni por el
encabezado que declare el servidor: se comprueba que los bytes sean de verdad una
imagen y de ahi se deduce el formato. El nombre sale del hash del contenido, de
modo que los nombres no son previsibles y dos productos que comparten la misma
foto no la duplican en disco.

Los medios descargados no se versionan —`storage/` esta en el `.gitignore`—, asi
que no engordan el repositorio y hay que copiarlos aparte al despliegue, o volver
a ejecutar el comando ahi.

## Envios

La tarifa unica nacional arranca en `$99.00 MXN` y el umbral de envio gratis en
`$800.00 MXN`, pero una vez sembrados la fuente de verdad es la configuracion
administrable, no el archivo de entorno. Se ajusta en **Configuracion > Envios**:
activar o desactivar los envios, nombre visible del metodo, tarifa, umbral, si el
umbral se compara antes o despues de descuentos, dias de preparacion, mensaje de
entrega estimada y estados o codigos postales sin cobertura.

Los valores de `.env` solo siembran la configuracion en la primera instalacion,
para que un despliegue nuevo pueda partir de otras cifras sin tocar codigo.
Volver a ejecutar los seeders no pisa lo que ya se haya ajustado en el panel.

El costo se calcula siempre en el servidor, en
`App\Domain\Shipping\Actions\CalculateShipping`. La tienda muestra un adelanto en
el navegador para que el cliente vea el total al instante, pero lo que se cobra
sale del servidor: hay una prueba que envia un envio de cero en el formulario y
comprueba que el pedido se guarda con la tarifa correcta.

## Codigos postales (SEPOMEX)

La captura de direcciones no depende de ninguna API de terceros: se consulta una
tabla local importada del catalogo oficial de Correos de Mexico.

Descarga el catalogo nacional desde
<https://www.correosdemexico.gob.mx/SSLServicios/ConsultaCP/CodigoPostal_Exportar.aspx>
(el archivo delimitado por barras verticales) y importalo:

```bash
php artisan sepomex:import ruta/al/CPdescarga.txt
```

Para reemplazar el catalogo completo en lugar de actualizarlo:

```bash
php artisan sepomex:import ruta/al/CPdescarga.txt --fresh
```

El importador lee en flujo e inserta por lotes, porque el catalogo nacional pasa
de las 145 mil filas y no cabe entero en el limite de memoria habitual de PHP.
Convierte la codificacion Windows-1252 del archivo a UTF-8 —sin eso los nombres
con acento llegan corrompidos—, rellena el cero inicial que el catalogo a veces
omite y usa `upsert`, asi que se puede volver a correr cuando Correos publique
una version nueva sin duplicar asentamientos.

En el checkout, al escribir los cinco digitos se consulta
`/codigo-postal/{cp}` sin recargar la pagina y se completan estado, municipio y
ciudad, con **todos** los asentamientos del codigo en un selector; recortar esa
lista dejaria al cliente sin poder elegir su colonia. La ruta lleva limite de
peticiones porque es publica y se llama a cada tecla.

Si el codigo no existe o la consulta falla, se habilita la captura manual con un
aviso comprensible. Una direccion que no se puede escribir es una venta perdida,
asi que el catalogo ayuda pero nunca bloquea.

Las pruebas no necesitan el catalogo nacional: usan
`tests/Fixtures/sepomex-muestra.txt`, que reproduce el formato oficial con unas
pocas filas, incluidas las que el importador debe descartar.

## Correo

El SMTP se captura en **Configuracion > Correo**. Una vez guardado, esa
configuracion manda sobre la del archivo de entorno, que queda solo como respaldo
de arranque. La contrasena se guarda cifrada y se muestra enmascarada; dejar el
campo vacio conserva la que ya estaba, de modo que cambiar otro dato no destruye
la credencial buena.

El boton **Enviar correo de prueba** guarda primero lo que hay en pantalla y
manda un mensaje sin pasar por la cola, porque una prueba tiene que decir ahora
mismo si funciona. Si falla, muestra el mensaje que devolvio el servidor de
correo: ahi ese detalle tecnico si es util, porque lo lee un administrador.

Correos que la tienda envia hoy:

| Momento | Destinatario |
|---|---|
| Pedido registrado, con instrucciones de pago y enlace de seguimiento | Cliente |
| El pago llega a un estado final (aprobado, rechazado, cancelado, expirado, reembolsado) | Cliente |
| Se acepta o se rechaza un comprobante de transferencia | Cliente |
| Venta nueva | La direccion interna que se configure |

Los estados intermedios no se avisan: recibir "procesando" no le dice nada util a
nadie. Un webhook repetido tampoco genera otro correo, porque el aviso sale solo
cuando el estado cambia de verdad.

**Ninguna falla de correo puede costar una venta.** Todos los correos van en cola
con tres reintentos y espera creciente, y el encolado va envuelto: si la cola es
inaccesible se registra el problema y la compra sigue su curso. Hay una prueba que
simula la cola caida y comprueba que el pedido se guarda igual.

## Cola de trabajos

Los correos no salen solos: hace falta que algo procese la cola. En local:

```bash
php artisan queue:work
```

En Hostinger, donde no se puede dar por hecho que exista un supervisor de
procesos, se programa por cron con un proceso que termina cuando vacia la cola:

```bash
php /home/USUARIO/domains/DOMINIO/chuta/artisan queue:work --stop-when-empty --max-time=55
```

Ejecutandolo cada minuto, el correo sale con un retraso maximo de un minuto y
ningun proceso queda colgado. Los trabajos que fallan quedan en la tabla
`failed_jobs` y se pueden reintentar:

```bash
php artisan queue:retry all
```

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
- [x] Etapa 2 — Identidad, acceso, roles y permisos: catalogo de permisos
      agrupados, roles sembrados de forma idempotente, superadministrador con
      acceso total, cuentas desactivables, correo normalizado, recuperacion de
      contrasena, limite de intentos, registro de ultimo acceso y cambio
      obligatorio de la contrasena inicial en produccion.
- [ ] Etapa 3 — Catalogo. Hay marcas, categorias, productos y sus pantallas del
      panel. Faltan variantes, etiquetas, imagenes multiples y datos SEO.
- [x] Etapa 4 — Inventario: historial inmutable de movimientos, descuento
      atomico al confirmar un pedido con bloqueo de fila para evitar sobreventa,
      reposicion controlada al cancelar o devolver, aviso de existencias bajas y
      ajuste manual trazable desde el panel. Las reservas durante el pago se
      dejan para la etapa de pagos, cuando exista una ventana de pago que
      reservar (ver [Inventario](#inventario)).
- [~] Etapa 5 — Escaparate rehecho con la identidad de la tienda y los cuatro
      temas definidos como design tokens (ver
      [Identidad visual y temas](#identidad-visual-y-temas)). Falta administrar
      desde el panel los carruseles, los bloques ordenables de la portada, el
      blog y la seleccion de tema.
- [ ] Etapa 6 — Busqueda, carrito y promociones.
- [x] Etapa 7 — Envios configurables desde el panel y captura de direcciones
      contra el catalogo local de codigos postales, con respaldo manual. Ver
      [Envios](#envios) y [Codigos postales](#codigos-postales-sepomex). El
      checkout de invitado ya funcionaba; falta la clave de idempotencia, que va
      junto con los pagos.
- [ ] Etapa 8 — Pedidos y cuentas de cliente.
- [ ] Etapa 9 — Pagos.
- [~] Etapa 10 — SMTP administrable con correo de prueba y correos
      transaccionales en cola (ver [Correo](#correo) y
      [Cola de trabajos](#cola-de-trabajos)). Falta Meta Pixel y Conversions API.
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
