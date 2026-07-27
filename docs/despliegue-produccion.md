# Puesta en produccion

Guia para dejar la tienda operando en Hostinger. Sigue el orden: cada paso asume
el anterior.

Anota el usuario y el dominio reales; en los comandos aparecen como
`USUARIO` y `DOMINIO`.

---

## 1. Base de datos

Crea la base y un usuario propio desde hPanel. **No uses el usuario root ni la
contrasena del entorno local.**

```sql
CREATE DATABASE chuta_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Dale a ese usuario permisos solo sobre esa base. Guarda la contrasena en un gestor
de contrasenas, no en un archivo suelto.

---

## 2. Subir el proyecto

Estructura recomendada: el nucleo de Laravel **fuera** de `public_html`.

```text
/home/USUARIO/domains/DOMINIO/
├── chuta/            <- el proyecto completo
└── public_html/      <- solo lo que debe ser publico
```

Si hPanel te deja apuntar el document root directamente a `chuta/public`, hazlo:
es la opcion preferida y entonces no necesitas tocar `public_html`.

Sube todo **menos** `node_modules`, `vendor`, `.env` y `storage/app/public`.

```bash
cd /home/USUARIO/domains/DOMINIO/chuta
composer install --no-dev --optimize-autoloader
```

---

## 3. Archivo de entorno

Copia la plantilla y genera la llave:

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env`:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://DOMINIO

DB_DATABASE=chuta_prod
DB_USERNAME=el_usuario_que_creaste
DB_PASSWORD=la_contrasena_que_guardaste
```

**`APP_DEBUG=false` no es opcional.** En true, cualquier error muestra rutas del
servidor, consultas y fragmentos de configuracion a quien visite la pagina.

---

## 4. Migraciones y datos iniciales

```bash
php artisan migrate --force
```

```bash
php artisan db:seed --force
```

Los seeders son idempotentes: crean roles, permisos, las cuentas iniciales, la
configuracion de envios y el catalogo. Ejecutarlos de nuevo no duplica nada.

### Cambia las contrasenas iniciales

Las cuentas se siembran con la contrasena `password`. En produccion nacen marcadas
para exigir el cambio en el primer acceso, pero **cambialas tu de inmediato** y
borra la que no vayas a usar:

- `superadmin@local.test`
- `admin@local.test`

Cambia tambien esos correos por los reales desde el panel.

---

## 5. Enlace de almacenamiento y assets

```bash
php artisan storage:link
```

```bash
npm ci && npm run build
```

Si el servidor no tiene Node, compila en tu maquina y sube la carpeta
`public/build` ya generada.

---

## 6. Traer las imagenes a este servidor

El catalogo puede seguir apuntando al sitio anterior. Mientras ese sitio siga en
pie, tramelas:

```bash
php artisan media:localize
```

En un servidor compartido conviene por tandas:

```bash
php artisan media:localize --limit=200
```

**Hazlo antes de apuntar el dominio a esta aplicacion.** Despues, las direcciones
del sitio anterior dejan de responder y el catalogo se queda sin fotos.

Alternativa: copiar por FTP la carpeta `storage/app/public` desde tu maquina.

---

## 7. Codigos postales

Descarga el catalogo nacional de Correos de Mexico (el archivo delimitado por
barras) desde su pagina de consulta de codigos postales, subelo al servidor e
importalo:

```bash
php artisan sepomex:import ruta/al/CPdescarga.txt
```

Sin esto, el checkout sigue funcionando con captura manual de direccion, pero el
cliente pierde el autocompletado de colonia, ciudad y estado.

---

## 8. Servidor web

Si el document root **no** apunta a `chuta/public`, copia a `public_html` el
contenido de `chuta/public` y ajusta las rutas de `index.php` para que apunten al
nucleo. El `.htaccess` de la raiz del proyecto ya cubre el caso de servir desde una
subcarpeta.

Comprueba que estas direcciones **no** sean accesibles desde el navegador:

- `https://DOMINIO/.env`
- `https://DOMINIO/composer.json`
- `https://DOMINIO/storage/logs/laravel.log`

Las tres deben responder 403 o 404. Si alguna se descarga, detente y corrige el
document root antes de seguir.

---

## 9. HTTPS

Activa el certificado gratuito desde hPanel y fuerza la redireccion a HTTPS. El
checkout maneja datos personales y direcciones: sin HTTPS viajan en claro.

---

## 10. Cron: la cola y las tareas

**Sin esto los correos se encolan y nunca salen.** Anade en hPanel un cron que
corra **cada minuto**:

```bash
php /home/USUARIO/domains/DOMINIO/chuta/artisan queue:work --stop-when-empty --max-time=55
```

Y otro, tambien cada minuto, para las tareas programadas:

```bash
php /home/USUARIO/domains/DOMINIO/chuta/artisan schedule:run
```

El primero termina solo al vaciar la cola, de modo que ningun proceso queda
colgado. Los trabajos que fallen quedan en la tabla `failed_jobs`:

```bash
php artisan queue:retry all
```

---

## 11. Correo

En el panel, **Configuracion > Correo**:

1. Captura el SMTP de tu dominio.
2. Pon un remitente que tu servidor tenga permitido usar. Uno de Gmail o Hotmail
   suele acabar en la carpeta de no deseados.
3. Opcional: una direccion interna para recibir aviso de cada venta.
4. Pulsa **Enviar correo de prueba** y confirma que llega.

Hasta que ese correo llegue, tus clientes no reciben confirmacion de sus pedidos.

---

## 12. Envios

En **Configuracion > Envios**: tarifa, umbral de envio gratis, dias de preparacion,
mensaje de entrega y estados o codigos postales sin cobertura.

Arranca en `$99.00` de tarifa y `$800.00` de umbral. Revisa que sean tus cifras
reales antes de abrir.

---

## 13. Pagos

En **Configuracion > Pagos**:

### Transferencia bancaria

Banco, beneficiario, CLABE y horas para pagar. Pulsa **Probar conexion**: valida
que los datos esten completos y que la CLABE tenga 18 digitos.

### Clip

1. Captura la API key y la clave secreta **de pruebas**, con **Modo pruebas
   activado**.
2. Copia la URL del webhook que muestra la pantalla y registrala en el panel de
   Clip.
3. Captura el secreto del webhook. **Sin el, los avisos de Clip se rechazan**
   porque no se pueden verificar.
4. Pulsa **Probar conexion**. Nunca genera un cobro.
5. **Haz una compra de prueba completa** y confirma que el pedido pasa a pagado.
6. Solo entonces cambia a credenciales de produccion y desactiva el modo pruebas.

> Clip esta probado con simulaciones, no contra su sandbox real. El paso 5 no es
> opcional.

Mercado Pago y PayPal todavia no tienen integracion: no aparecen en el checkout.

---

## 14. Optimizar

Con todo configurado:

```bash
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

**Repite estos comandos cada vez que cambies `.env`**, o los cambios no surten
efecto.

---

## 15. Comprobacion final

Antes de anunciar la tienda, recorre esto como cliente:

- [ ] La portada carga con imagenes.
- [ ] El buscador encuentra productos.
- [ ] La pagina de un producto abre y se puede agregar al carrito.
- [ ] El checkout completa un pedido con transferencia.
- [ ] Llega el correo de confirmacion con el enlace de seguimiento.
- [ ] El enlace de seguimiento abre y permite subir un comprobante.
- [ ] En el panel aparece el comprobante y al aceptarlo el pedido queda confirmado.
- [ ] El inventario bajo tras la compra.
- [ ] `https://DOMINIO/ruta-inventada` muestra la pagina 404 de la tienda.
- [ ] `https://DOMINIO/.env` no se descarga.

---

## Respaldos

Programa desde hPanel un respaldo diario de la base y de `storage/app/public`.
Prueba **restaurar** uno antes de necesitarlo: un respaldo que nunca se restauro
no es un respaldo.

---

## Si algo falla

| Sintoma | Causa habitual |
|---|---|
| Error 500 en productos, pedidos o comprobantes | Falta la extension `intl`. Activala y reinicia el servidor web. |
| Los correos no llegan | Falta el cron de la cola, o el SMTP no esta configurado. |
| Un cambio de `.env` no surte efecto | Falta volver a ejecutar `config:cache`. |
| Los productos aparecen sin foto | Falta ejecutar `media:localize` o copiar `storage/app/public`. |
| Clip no confirma los pagos | Falta el secreto del webhook o registrar la URL en su panel. |
| Todas las rutas dan 404 | El document root no apunta donde debe. |

Los errores quedan en `storage/logs/laravel.log`. Con `APP_DEBUG=false` el cliente
ve una pagina cuidada y el detalle solo queda en ese archivo.
