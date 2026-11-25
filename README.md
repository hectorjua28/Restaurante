# Antojería Pingüin · Sistema de pedidos

Sistema de gestión de pedidos para **Antojería Pingüin** (Papantla, Veracruz).

| Parte | Tecnología | Quién la usa |
|---|---|---|
| **Sitio web** (`/web`) | Laravel 12, MySQL, Sanctum, Blade + Livewire 3, Tailwind 4 | Cocina (tablero) y administración |
| **API REST** (`/web`, `/api/v1`) | Laravel 12 + Sanctum (tokens) | App de meseros |
| **App de meseros** (`/mobile`) | Ionic 9 + Angular 22 (standalone, signals), PWA y Capacitor (Android) | Meseros, en su teléfono |
| **Prueba de extremo a extremo** (`/e2e`) | Playwright | CI |

Toda la lógica de negocio (totales, estados, permisos) vive en el servidor. La app y los paneles solo muestran lo que el servidor calcula.

---

## Contenido

- [Requisitos](#requisitos)
- [Instalación local](#instalación-local)
- [Credenciales de prueba](#credenciales-de-prueba)
- [Pruebas y calidad](#pruebas-y-calidad)
- [App de meseros: PWA y Android](#app-de-meseros-pwa-y-android)
- [Documentación de la API](#documentación-de-la-api)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Decisiones tomadas](#decisiones-tomadas)
- [Despliegue](#despliegue)
- [Lista de verificación](CHECKLIST.md)

## Requisitos

| Herramienta | Versión | Notas |
|---|---|---|
| PHP | 8.2 o superior | Extensiones: `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `zip` |
| Composer | 2.x | |
| MySQL / MariaDB | MySQL 8 (producción) o MariaDB 10.4+ (XAMPP) | |
| Node.js | 22 o 24 | Solo para compilar assets y la app (no se usa en el servidor) |
| JDK 21 + Android SDK | Opcional | Solo para compilar el APK |

## Instalación local

### 1. Sitio web y API

```bash
cd web
composer install
cp .env.example .env
php artisan key:generate
```

Crea la base de datos (con XAMPP encendido):

```bash
# Windows con XAMPP
C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE pinguin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Migra y siembra los datos de ejemplo:

```bash
php artisan migrate --seed
```

Esto crea 10 mesas, el menú típico (bocoles, picadas, enchiladas, zacahuil, café de olla, aguas frescas…), un usuario por rol y **7 días de órdenes cobradas** con sus cortes de caja, para que los reportes muestren datos. También deja algunas órdenes activas de hoy.

Compila los assets y levanta el servidor:

```bash
npm install
npm run build          # o "npm run dev" mientras desarrollas
php artisan serve      # http://localhost:8000
```

- Cocina y administración: <http://localhost:8000/login>

Para volver a empezar con datos limpios: `php artisan migrate:fresh --seed`.

### 2. App de meseros

```bash
cd mobile
npm install
npm start              # http://localhost:4200 (usa la API en http://localhost:8000/api/v1)
```

Para verla en tu teléfono dentro de la misma red Wi-Fi:

1. Levanta Laravel con `php artisan serve --host=0.0.0.0`.
2. En la app toca **Configurar servidor** y escribe `http://<IP-de-tu-PC>:8000`.

También puedes servirla desde Laravel, igual que en producción:

```bash
cd mobile
npm run build:pwa      # la publica en http://localhost:8000/app
```

## Credenciales de prueba

Todas las cuentas usan la contraseña **`pinguin123`**. Solo se crean con el seeder; **no se usan en producción** (ver [DEPLOY.md](DEPLOY.md)).

| Rol | Correo | Dónde entra |
|---|---|---|
| Administrador | `admin@pinguin.test` | <http://localhost:8000/login> → Panel de administración |
| Cocina | `cocina@pinguin.test` | <http://localhost:8000/login> → Tablero de cocina |
| Mesero | `mesero@pinguin.test` | App de meseros |
| Mesera | `mesera@pinguin.test` | App de meseros |

## Pruebas y calidad

### Backend (`/web`)

```bash
composer test                  # Pest: 182 pruebas (SQLite en memoria)
composer lint                  # Pint (estilo) + Larastan nivel 6
composer format                # aplica Pint
```

Para correr las pruebas contra MySQL/MariaDB (como en CI):

```bash
DB_CONNECTION=mysql DB_DATABASE=pinguin_test ./vendor/bin/pest
```

### App (`/mobile`)

```bash
npm test                       # Vitest: 53 pruebas de los servicios
npm run lint                   # ESLint + Prettier
```

### Extremo a extremo (`/e2e`)

El mesero envía una comanda, cocina la prepara y la marca lista, el mesero la entrega y cobra, y administración ve la orden cobrada.

```bash
cd web && npm run build                 # assets del sitio
cd ../mobile && npm run build:pwa       # PWA en web/public/app
cd ../e2e
npm install
npx playwright install chromium
npm test
```

Usa su propia base SQLite (`web/database/e2e.sqlite`), así que no toca tus datos.

### Integración continua

En cada push, GitHub Actions (`.github/workflows/ci.yml`) ejecuta:

| Job | Qué hace |
|---|---|
| `web` | Pint, Larastan, `migrate:fresh --seed` y Pest sobre **MySQL 8** |
| `mobile` | ESLint, Prettier, Vitest y build de producción |
| `android` | Compila el APK de prueba y lo publica como artefacto |
| `e2e` | Prueba de Playwright del flujo completo |

## App de meseros: PWA y Android

- **PWA instalable:** se publica en `https://<dominio>/app` con `npm run build:pwa`. En Chrome para Android aparece "Instalar app".
- **Sin conexión:** si no hay señal al enviar, la comanda se guarda en el teléfono (IndexedDB) y se envía sola al volver la red, con su misma `idempotency_key`, así que el servidor nunca la duplica. Un aviso en el encabezado muestra cuántas comandas faltan por enviar. Las mesas y el menú también quedan guardados para trabajar sin señal.
- **Android:** `npm run build:android` y luego `cd android && ./gradlew assembleDebug` (requiere JDK 21). La primera vez la app pide la dirección del servidor. Más detalles en [DEPLOY.md](DEPLOY.md#10-app-de-android-apk).

## Documentación de la API

- Interactiva (Scramble): <http://localhost:8000/docs/api>. En producción solo la ve el administrador.
- Especificación OpenAPI 3.1: [`docs/openapi.json`](docs/openapi.json). Para regenerarla: `php artisan scramble:export --path=../docs/openapi.json`.

Resumen de endpoints (`/api/v1`, token Bearer):

| Rol | Método y ruta |
|---|---|
| Público | `POST /auth/login` |
| Con sesión | `GET /auth/me`, `POST /auth/logout` |
| Mesero | `GET /tables`, `GET /tables/{id}`, `GET /tables/{id}/bill`, `POST /tables/{id}/payments` |
| Mesero | `GET /categories`, `GET /products` |
| Mesero | `GET /orders`, `POST /orders`, `GET /orders/{id}`, `POST /orders/{id}/deliver`, `POST /orders/{id}/cancel` |
| Cocina / admin | `GET /kitchen/orders`, `POST /kitchen/orders/{id}/advance`, `PATCH /products/{id}/availability` |

## Estructura del proyecto

```
/web                         Laravel
  app/Actions/               Lógica de negocio: órdenes, cobro, corte de caja, catálogos
  app/Enums/                 Rol, estado de orden, estado de mesa, estación, método de pago
  app/Http/                  Controladores delgados, Form Requests, Resources, middleware de rol
  app/Livewire/              Panel de cocina y panel de administración
  app/Policies/              Permisos por recurso
  app/Services/Reports/      Reportes de ventas y tiempos
  database/                  Migraciones, factories y seeders
  tests/                     Pest (Feature y Unit)
/mobile                      Ionic + Angular
  src/app/core/              API, autenticación, interceptor, comanda, cola sin conexión
  src/app/pages/             Login, Mesas, Comanda, Órdenes, Cobro
  android/                   Proyecto nativo de Capacitor
/e2e                         Playwright
/docs/openapi.json           Especificación de la API
```

## Decisiones tomadas

| Tema | Decisión |
|---|---|
| Versión | **Laravel 12**, compatible con el PHP 8.2 local y con Hostinger. |
| Dinero | **Centavos enteros** (`*_cents`) en la base, la API y la app. Se formatea a MXN solo al mostrar. Los montos que se capturan se convierten sin `float`. |
| Fechas | Se guardan en **UTC**; se muestran en `America/Mexico_City`. |
| Cobro | **Por mesa**: se cobran juntas todas sus órdenes entregadas. Se permite **dividir la cuenta** en varios pagos (efectivo, tarjeta o transferencia) y la suma debe ser exacta. |
| Propina | Se registra **por pago**, aparte del monto de venta. |
| Modificadores | Cada uno tiene un **precio extra** (0 por defecto). El precio y los modificadores se congelan en cada renglón de la orden. |
| Corte de caja | Incluye **todos los pagos desde el corte anterior**. Efectivo esperado = ventas en efectivo + propinas en efectivo. |
| Estados | El estado es de **toda la orden**. `cancelada` solo desde `pendiente`, con motivo. La mesa pasa a **por cobrar** cuando todas sus órdenes activas están entregadas. |
| Roles | Los meseros no entran al sitio web; el administrador puede ver el panel de cocina. Cualquier mesero puede cobrar cualquier mesa, pero solo el dueño de la orden la entrega o la cancela. |
| Mesas en código | El modelo se llama `DiningTable` (tabla `tables`) porque `table` es una propiedad reservada de Eloquent. |
| Tiempo real | Polling: cocina cada 4 s, app cada 5 s, resumen de administración cada 10 s. |
| Sesión de la app | Los tokens caducan a los **30 días** (`SANCTUM_TOKEN_EXPIRATION`). Al desactivar a un usuario se revocan sus tokens. |
| Bitácora | Solo inserción: el modelo impide editar o borrar. Guarda creación y cancelación de órdenes, cobros, cortes, cambios de disponibilidad y cada alta, edición o baja de catálogos y usuarios (con el antes y el después). |
| Producción | El administrador se crea con `php artisan pinguin:crear-admin`, que pide la contraseña en pantalla. |

## Logo

El logo es provisional. Reemplázalo por el real en:

- `web/public/img/logo.png` (sitio web)
- `mobile/src/assets/logo.png` (app)
- Íconos de la PWA: `mobile/public/icons/*.png`
- Íconos de Android: `mobile/android/app/src/main/res/mipmap-*/`

## Despliegue

Guía paso a paso para Hostinger en **[DEPLOY.md](DEPLOY.md)**: SSH, `composer install --no-dev`, migraciones, cachés, cron del scheduler, `.env` de producción y APK.
