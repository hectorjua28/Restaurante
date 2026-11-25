# Lista de verificación

Cada funcionalidad del documento de requisitos, con la prueba automática que la cubre.

**Abreviaturas de archivos de prueba:**

| Abreviatura | Archivo |
|---|---|
| `Auth/Web` | `web/tests/Feature/Auth/WebLoginTest.php` |
| `Auth/Api` | `web/tests/Feature/Auth/ApiAuthTest.php` |
| `Api/Catalog` | `web/tests/Feature/Api/CatalogApiTest.php` |
| `Api/Order` | `web/tests/Feature/Api/OrderApiTest.php` |
| `Api/Payment` | `web/tests/Feature/Api/PaymentApiTest.php` |
| `Kitchen` | `web/tests/Feature/Kitchen/KitchenBoardTest.php` |
| `Admin` | `web/tests/Feature/Admin/AdminPanelTest.php` |
| `Cash` | `web/tests/Feature/Admin/CashReportsAuditTest.php` |
| `Unit/…` | `web/tests/Unit/…` |
| `m:…` | `mobile/src/app/**/*.spec.ts` |
| `E2E` | `e2e/tests/flujo-completo.spec.ts` |

**Leyenda:**
- ✅ Hecho y cubierto por una prueba automática.
- ✅ 👁 Hecho y verificado a mano en el navegador (con capturas durante el desarrollo); no tiene prueba automática propia.

---

## Restricciones

| Requisito | Estado | Prueba |
|---|---|---|
| Solo PHP, MySQL, Composer, SSH y cron en producción | ✅ | [DEPLOY.md](DEPLOY.md). Assets compilados en local; CI con MySQL 8 |
| "Tiempo real" con polling de 3 a 5 s | ✅ | `Kitchen` › se actualiza solo con polling (4 s); app cada 5 s (`m:my-orders.service`) |
| Solo herramientas gratuitas o de código abierto | ✅ | Todas las dependencias son open source |
| Español de México, MXN y America/Mexico_City | ✅ | `Unit/MoneyTest`; `Kitchen` › muestra el tablero (hora 12:00 local); `Auth/Api` › valida en español; `m:format` › hora en Ciudad de México |
| Fechas guardadas en UTC y convertidas al mostrar | ✅ | `Cash` › ventas por día en hora local; API con ISO `Z` (`Api/Order` › calcula el total…) |
| Dinero sin `float` (centavos enteros) | ✅ | `Unit/MoneyTest` › convierte sin float; `m:format` › convierte pesos sin flotantes |
| Totales, estados y permisos validados en el backend | ✅ | `Api/Order` › calcula el total en el servidor (ignora el precio del cliente); `Api/Payment` › rechaza pagos que no suman |
| Nunca se muestra "undefined", "null" ni "NaN" | ✅ | `E2E` (revisa las tres pantallas); `Kitchen` › tablero; `Admin` › resumen; `m:format` › nunca muestra NaN |
| Sin pestañas a medias | ✅ 👁 | Cada pestaña o menú se agregó solo al terminar su funcionalidad |

## Stack y estructura

| Requisito | Estado | Prueba |
|---|---|---|
| Monorepo `/web` (Laravel 12, MySQL 8, Sanctum, Livewire, Tailwind) | ✅ | CI job `web` |
| `/mobile` (Ionic + Angular standalone, PWA y Capacitor Android) | ✅ | CI jobs `mobile` y `android` |
| Form Requests, Policies, Resources, Actions y controladores delgados | ✅ | `app/Http/Requests`, `app/Policies`, `app/Http/Resources`, `app/Actions` |
| Servicios de Angular, interceptor de token con manejo del 401, estado con signals | ✅ | `m:auth.spec` › interceptor agrega el token; al recibir 401 cierra sesión |

## Roles y permisos

| Requisito | Estado | Prueba |
|---|---|---|
| Rutas protegidas por rol (403) | ✅ | `Api/Catalog` › solo meseros usan las rutas de la app / meseros no usan las de cocina; `Auth/Web` › protege los paneles por rol; `Admin` › solo el administrador entra; `Kitchen` › solo cocina y administración |
| 401 sin sesión | ✅ | `Api/Catalog` › responde 401 sin token; `Auth/Api` › responde 401 en español |
| Mesero: ve mesas, toma comandas, consulta, entrega, cancela y cobra | ✅ | `Api/Catalog`, `Api/Order`, `Api/Payment`, `E2E` |
| Cocina: ve órdenes, cambia el estado y marca agotados | ✅ | `Kitchen` (completo) |
| Administrador: administración, reportes y corte | ✅ | `Admin`, `Cash` |

## Modelo de datos

| Tabla | Estado | Prueba |
|---|---|---|
| `users` (rol, activo) | ✅ | `Auth/Web` › rechaza a un usuario desactivado |
| `tables` (libre, ocupada, por cobrar) | ✅ | `Api/Order` › la mesa pasa a «por cobrar» |
| `categories` y `products` (estación, disponible, soft delete) | ✅ | `Api/Catalog` › no muestra platillos eliminados; `Admin` › elimina (soft delete) |
| `modifiers` | ✅ | `Admin` › crea un platillo con modificadores |
| `orders` (`idempotency_key` única, motivo de cancelación) | ✅ | `Api/Order` › idempotencia; cancelación |
| `order_items` (precio unitario al momento de la venta) | ✅ | `Api/Order` › guarda el precio al momento de la venta |
| `order_status_history` | ✅ | `Api/Order` › registra quién y desde qué estado |
| `payments` (método, monto, propina, usuario) | ✅ | `Api/Payment` › cobra la mesa con un solo pago y propina |
| `cash_closings` | ✅ | `Cash` › corte de caja |
| `audit_logs` (solo inserción) | ✅ | `SeederTest` › la bitácora es solo de inserción; `Cash` › es de solo lectura |

## Reglas de negocio

| Regla | Estado | Prueba |
|---|---|---|
| Pendiente → en preparación → lista → entregada → cobrada, sin saltos ni retrocesos | ✅ | `Unit/OrderStatusTest` (todo el archivo); `Api/Order` › cocina avanza…, el mesero solo entrega órdenes listas |
| El mesero solo cancela pendientes y con motivo | ✅ | `Api/Order` › no cancela órdenes en preparación; exige el motivo |
| Una orden cobrada no se puede modificar | ✅ | `Api/Payment` › una orden cobrada no se puede modificar |
| Al enviar, la mesa pasa a ocupada; al cobrar todo, a libre | ✅ | `Api/Order` › pone la mesa como ocupada; `Api/Payment` › cobra la mesa (mesa libre) |
| Un platillo agotado no entra en comandas nuevas y no afecta las ya enviadas | ✅ | `Api/Order` › rechaza platillos agotados; marcar agotado no afecta las órdenes enviadas |
| Misma `idempotency_key` devuelve la orden existente | ✅ | `Api/Order` › devuelve la misma orden si llega dos veces la misma clave |
| Transacciones y `lockForUpdate` de la mesa al crear y cobrar | ✅ | `CreateOrder` y `ChargeTable` bloquean la mesa. `Api/Payment` › no cobra dos veces la misma mesa; `Api/Payment` › rechaza pagos que no suman (nada se guarda: rollback). La concurrencia real se cubre con el bloqueo; no hay una prueba de dos procesos en paralelo |
| Cada cambio de estado queda en `order_status_history` | ✅ | `Api/Order` › registra quién y desde qué estado; `Kitchen` › registrando el historial |
| Cancelaciones, cobros y ediciones en `audit_logs` | ✅ | `Api/Order` › cancela… y queda en la bitácora; `Api/Payment` › cobra la mesa (`table.charged`); `Admin` › edita un platillo, registra los cambios |

## Sitio web

### Inicio de sesión

| Requisito | Estado | Prueba |
|---|---|---|
| Común para cocina y administración, redirige según el rol | ✅ | `Auth/Web` › redirige a cocina… / al administrador… |
| Límite de intentos | ✅ | `Auth/Web` › bloquea después de 5 intentos; `Auth/Api` › responde 429 |

### Panel de cocina

| Requisito | Estado | Prueba |
|---|---|---|
| Tres columnas en orden de llegada | ✅ | `Kitchen` › muestra el tablero en tres columnas, en orden de llegada |
| Tarjeta: mesa, mesero, hora, tiempo transcurrido, platillos, cantidades, modificadores y notas | ✅ | `Kitchen` › …con todos los datos de la tarjeta; `E2E` |
| Botones grandes para cambiar el estado | ✅ | `Kitchen` › avanza una orden…; `E2E` |
| Filtro por estación | ✅ | `Kitchen` › filtra por estación |
| Platillos agotados o disponibles | ✅ | `Kitchen` › platillos agotados (2 pruebas) |
| Polling con indicador de "sin conexión" | ✅ 👁 | `Kitchen` › se actualiza solo con polling (verifica `wire:poll` y el aviso). El aviso se probó en el navegador cortando la red |

### Panel de administración

| Requisito | Estado | Prueba |
|---|---|---|
| Resumen: recaudado del día, órdenes activas y cerradas, ocupación, últimas órdenes y pagos | ✅ | `Admin` › resumen (3 pruebas) |
| CRUD de platillos con modificadores | ✅ | `Admin` › platillos (4 pruebas) |
| CRUD de categorías y mesas | ✅ | `Admin` › categorías y mesas (3 pruebas) |
| CRUD de usuarios | ✅ | `Admin` › usuarios (4 pruebas) |
| Órdenes: filtros por fecha, estado, mesa y mesero; detalle con historial | ✅ | `Admin` › filtra por fecha, estado, mesa y mesero; muestra el detalle con historial |
| Pagos por fecha y método | ✅ | `Admin` › lista pagos por fecha y método con totales |
| Corte de caja: totales por método, efectivo contado y diferencia | ✅ | `Cash` › corte de caja (4 pruebas) |
| Reportes: ventas por rango, más vendidos, por método, tiempo de preparación y total por orden (con `order_status_history`) | ✅ | `Cash` › reportes (calcula ventas…, calcula tiempos…) |
| Exportación a CSV | ✅ | `Cash` › exporta cada reporte a CSV (4 casos); valida fechas y tipo |
| Bitácora de solo lectura | ✅ | `Cash` › bitácora (2 pruebas) |

## App móvil del mesero

| Requisito | Estado | Prueba |
|---|---|---|
| Cuatro pestañas: Mesas, Comanda, Órdenes y Cobro | ✅ | `E2E` (usa Mesas, Comanda, Órdenes y Cobro) |
| Login con correo y contraseña; la sesión se mantiene y se cierra con 401 | ✅ | `m:auth.spec` › guarda la sesión; al recibir 401 cierra la sesión |
| Mesas: cuadrícula por color, contadores y filtros | ✅ 👁 | `Api/Catalog` › contadores y filtro (servidor). Pantalla revisada en el navegador; el `E2E` la usa para elegir mesa |
| Comanda: mesa, buscador y categorías | ✅ | `E2E` (busca "enchiladas verdes") |
| Modificadores y nota al agregar | ✅ | `E2E`; `m:order-draft.service` › junta renglones iguales… |
| Cantidades, total parcial y confirmación de "Enviar a cocina" | ✅ | `m:order-draft.service` › calcula el total parcial; `E2E` ($200.00 y alerta "Enviar") |
| Platillos agotados deshabilitados | ✅ 👁 | `Api/Catalog` › incluye agotados con `available: false`. El deshabilitado se revisó en el navegador |
| Órdenes por estado con aviso cuando una está lista | ✅ | `m:my-orders.service` › avisa solo cuando una orden pasa a lista; `E2E` (insignia de lista) |
| Marcar entregada y cancelar pendiente con motivo | ✅ | `m:my-orders.service` › marca entregada / cancela con motivo; `E2E` |
| Cobro: mesa ocupada, detalle y total del servidor, método de pago y confirmación | ✅ | `m:checkout` (6 pruebas); `E2E` |
| Sin conexión: comanda en IndexedDB, envío automático con `idempotency_key` y contador de pendientes | ✅ | `m:offline-queue.service` (7 pruebas). Además se probó en el navegador cortando y restaurando la red |
| Uso con una mano: botones ≥ 44 px, texto legible en 6" | ✅ 👁 | Estilos globales (`ion-button` ≥ 44 px, botones grandes de 52 px); revisado en un Pixel 7 emulado |

## Diseño visual

| Requisito | Estado | Prueba |
|---|---|---|
| Pizarra #2E3B44, amarillo #E8C531 y rojo #C0392B | ✅ 👁 | `web/resources/css/app.css` y `mobile/src/theme/variables.scss` |
| Fondo claro y tarjetas blancas redondeadas | ✅ 👁 | Ídem |
| Estados con color consistente (pendiente amarillo, preparación azul, lista verde, cancelada gris) | ✅ | `Unit/OrderStatusTest` › tiene etiqueta y color para cada estado; mismas clases en web y app |
| Logo en `/web/public/img/logo.png` y `/mobile/src/assets/logo.png` | ✅ | Logo provisional en ambas rutas |

## Calidad

| Requisito | Estado | Prueba |
|---|---|---|
| Pest para cada endpoint y cada regla | ✅ | 182 pruebas (`composer test`) |
| Laravel Pint y Larastan | ✅ | `composer lint` (Larastan nivel 6, sin errores) |
| Documentación OpenAPI | ✅ | `Api/ApiDocsTest`; `docs/openapi.json` |
| Pruebas de los servicios de la app | ✅ | 53 pruebas (`npm test`) |
| ESLint y Prettier | ✅ | `npm run lint` |
| Prueba de extremo a extremo con Playwright | ✅ | `E2E` › el mesero envía, cocina prepara, el mesero cobra |
| GitHub Actions en cada push | ✅ | `.github/workflows/ci.yml` (web, mobile, android, e2e) |
| Seeders: 10 mesas, menú típico, un usuario por rol y órdenes de días anteriores | ✅ | `SeederTest` (6 pruebas) |

## Hitos y entregables

| Entregable | Estado |
|---|---|
| Hitos 1 a 9 con pruebas y commit | ✅ (ver `git log`) |
| `README.md`: instalación, sembrado, web y app en local, pruebas y despliegue | ✅ |
| `DEPLOY.md`: SSH, `composer install --no-dev`, migraciones, cachés, cron y `.env` de producción | ✅ |
| Credenciales de prueba por rol | ✅ ([README](README.md#credenciales-de-prueba)) |
| Lista de verificación | ✅ (este archivo) |
