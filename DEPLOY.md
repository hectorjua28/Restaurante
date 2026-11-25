# Despliegue en Hostinger (hosting compartido)

Guía para publicar **Antojería Pingüin** en un plan de Hostinger con SSH (Premium, Business o superior).
En el servidor solo se usa **PHP, MySQL, Composer, SSH y cron**: no hace falta Node, Docker ni procesos permanentes.
Los assets (CSS/JS del sitio y la PWA de meseros) se compilan en tu computadora y se suben ya listos.

> En los comandos, reemplaza `<usuario>`, `<ip>`, `<dominio>` y `u123456789` por tus datos de hPanel.
> Hostinger usa el puerto SSH **65002**.

---

## 1. Preparar Hostinger (hPanel)

1. **PHP 8.2 o superior:** *Sitios web → Administrar → Avanzado → Configuración de PHP*. Elige 8.2 o 8.3 y verifica que estén activas las extensiones `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `tokenizer`, `xml`, `ctype` y `zip`.
2. **Base de datos:** *Bases de datos → Administración*. Crea una base y un usuario MySQL; anota nombre, usuario y contraseña.
3. **SSH:** *Avanzado → Acceso SSH → Habilitar*. Anota IP, usuario y puerto (65002).
4. **SSL:** *Seguridad → SSL*. Instala el certificado gratuito y activa **Forzar HTTPS**. La app de meseros necesita HTTPS para instalarse como PWA.

## 2. Subir el código por SSH

```bash
ssh -p 65002 <usuario>@<ip>
cd ~/domains/<dominio>
git clone https://github.com/hectorjua28/Restaurante.git pinguin
cd pinguin/web
composer install --no-dev --optimize-autoloader --no-interaction
```

Si `composer` no existe como comando, usa `php ~/composer.phar` o `composer2` (Hostinger incluye Composer 2).

## 3. Configurar el `.env` de producción

```bash
cp .env.production.example .env
nano .env          # llena APP_URL, DB_DATABASE, DB_USERNAME y DB_PASSWORD
php artisan key:generate --force
chmod -R 775 storage bootstrap/cache
```

Puntos importantes del `.env`:

| Variable | Valor en producción |
|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` (nunca `true` en producción) |
| `APP_URL` | `https://<dominio>` |
| `DB_*` | Los datos de la base creada en hPanel (`DB_HOST=localhost`) |
| `SESSION_DRIVER` / `CACHE_STORE` | `database` (en hosting compartido no hay Redis) |
| `SESSION_SECURE_COOKIE` | `true` (requiere HTTPS) |
| `SANCTUM_TOKEN_EXPIRATION` | Minutos que dura la sesión de la app (43200 = 30 días) |

## 4. Compilar y subir los assets (en tu computadora)

En el servidor no hay Node, así que se compila localmente:

```bash
# Sitio web (Tailwind + JS)
cd web
npm ci
npm run build                       # genera web/public/build

# App de meseros como PWA, publicada en https://<dominio>/app
cd ../mobile
npm ci
npm run build:pwa                   # genera web/public/app
```

Sube ambas carpetas al servidor:

```bash
cd ../web
scp -P 65002 -r public/build public/app <usuario>@<ip>:~/domains/<dominio>/pinguin/web/public/
```

## 5. Base de datos

```bash
cd ~/domains/<dominio>/pinguin/web
php artisan migrate --force

# Catálogos iniciales (opcional): 10 mesas y el menú típico
php artisan db:seed --class=TableSeeder --force
php artisan db:seed --class=MenuSeeder --force

# Administrador con contraseña segura (la pide en pantalla)
php artisan pinguin:crear-admin admin@<dominio> "Nombre del administrador"
```

> ⚠️ **No ejecutes `php artisan db:seed` sin `--class` en producción.** Crea órdenes de ejemplo y usuarios con la contraseña de prueba `pinguin123`.
> Los usuarios de cocina y los meseros se dan de alta desde **Administración → Usuarios**.

## 6. Cachés de producción

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Cada vez que cambies el `.env`, vuelve a ejecutar `php artisan config:cache`.

## 7. Apuntar el dominio a `web/public`

El sitio debe servirse desde `web/public` (nunca desde la raíz del proyecto). En Hostinger lo más sencillo es reemplazar `public_html` por un enlace simbólico:

```bash
cd ~/domains/<dominio>
mv public_html public_html_respaldo
ln -s ~/domains/<dominio>/pinguin/web/public public_html
```

Comprueba:

- `https://<dominio>/login` → inicio de sesión de cocina y administración.
- `https://<dominio>/app` → app de meseros (PWA). En Android/Chrome aparece **"Instalar app"**.
- `https://<dominio>/up` → responde 200.

## 8. Cron del scheduler

*hPanel → Avanzado → Cron Jobs → Personalizado*, cada minuto (`* * * * *`):

```
/usr/bin/php /home/u123456789/domains/<dominio>/pinguin/web/artisan schedule:run >> /dev/null 2>&1
```

Tareas que ejecuta (ver `web/routes/console.php`), a las 4:00 a. m. hora de México:

- `sanctum:prune-expired`: borra los tokens caducados de la app.
- `auth:clear-resets`: limpia los tokens de restablecimiento de contraseña vencidos.
- `session:gc`: borra las sesiones viejas.

Para verificarlo: `php artisan schedule:list`.

## 9. Actualizar a una nueva versión

```bash
# En tu computadora: compila y sube los assets (paso 4)
# En el servidor:
cd ~/domains/<dominio>/pinguin
php web/artisan down --retry=30
git pull
cd web
composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize:clear && php artisan optimize && php artisan view:cache
php artisan up
```

## 10. App de Android (APK)

El APK es la misma app empaquetada con Capacitor. Como dentro del APK no hay dominio propio, **la primera vez pide la dirección del servidor** en el inicio de sesión (`https://<dominio>`; la app completa `/api/v1`).

APK de prueba (debug):

```bash
cd mobile
npm run build:android
cd android
./gradlew assembleDebug      # Windows: gradlew.bat assembleDebug
# Resultado: android/app/build/outputs/apk/debug/app-debug.apk
```

- Requiere **JDK 21** (`JAVA_HOME`) y el Android SDK (`ANDROID_HOME`).
- GitHub Actions también compila el APK de prueba en cada push: descárgalo desde la ejecución del workflow → *Artifacts → pinguin-meseros-debug-apk*.

APK firmado para distribuir:

```bash
keytool -genkey -v -keystore pinguin.keystore -alias pinguin -keyalg RSA -keysize 2048 -validity 10000
# Guarda pinguin.keystore y sus contraseñas fuera del repositorio.
cd mobile/android
./gradlew assembleRelease
# Firma con apksigner (Android SDK build-tools) usando pinguin.keystore
```

Para probar el APK contra tu computadora en la red local (API por `http://`), compila con `PINGUIN_ALLOW_HTTP=1 npm run build:android`. No lo uses para producción.

## 11. Respaldos

- hPanel incluye respaldos automáticos (*Archivos → Respaldos*).
- Respaldo propio diario de la base con cron (opcional):

```
0 5 * * * mysqldump -u <usuario_bd> -p'<contraseña_bd>' <base> | gzip > /home/u123456789/respaldos/pinguin-$(date +\%F).sql.gz
```

## 12. Problemas comunes

| Síntoma | Solución |
|---|---|
| Error 500 en blanco | Revisa `web/storage/logs/laravel-*.log`; verifica permisos: `chmod -R 775 storage bootstrap/cache`. |
| Estilos rotos en el sitio | Falta subir `public/build` (paso 4). |
| `/app` responde 404 | Falta subir `public/app` (`npm run build:pwa`). |
| Cambié el `.env` y no se nota | `php artisan config:cache`. |
| La app dice "Sin conexión con el servidor" | Verifica `https://<dominio>/up` y la dirección configurada en la app. |
| Error "419 Page Expired" al iniciar sesión | `APP_URL` debe coincidir con el dominio y usar HTTPS. |
