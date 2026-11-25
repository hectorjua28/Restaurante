# Prompt para Claude Code — Sitio web y app móvil

**Cómo usarlo:** abre Claude Code en una carpeta vacía y pega todo lo que está debajo de la línea. Claude primero te mostrará un plan; revísalo, respóndele sus preguntas y después déjalo avanzar hito por hito.

---

Actúa como desarrollador full-stack senior. Vas a construir desde cero un sistema de gestión de pedidos para un restaurante real: **Antojería Pingüin**, en Papantla, Veracruz. El sistema tiene dos partes:

1. **Sitio web (Laravel):** la API REST, el panel de cocina y el panel de administración.
2. **App móvil (Ionic + Angular):** la usan los meseros en su propio teléfono para tomar pedidos y cobrar.

Es un proyecto escolar, pero quiero calidad profesional: código limpio, reglas de negocio en el servidor, pruebas automáticas y nada a medias.

## Antes de programar

1. Hazme las preguntas que necesites si algo no está claro.
2. Muéstrame un plan corto: estructura de carpetas, modelo de datos (diagrama ER en Mermaid), lista de endpoints y orden de los hitos.
3. Espera mi aprobación. Después trabaja **un hito a la vez**; al terminar cada uno, corre las pruebas, muéstrame el resultado y haz commit.

## Restricciones

- **Producción en hosting compartido (Hostinger):** solo PHP, MySQL, SSH, Composer y cron. Sin Docker, sin WebSockets, sin procesos permanentes ni Node en el servidor. Las actualizaciones "en tiempo real" se hacen con polling cada 3 a 5 segundos.
- **Sin servicios de pago:** solo herramientas gratuitas o de código abierto.
- **Localización:** interfaz en español de México, moneda MXN, zona horaria America/Mexico_City. Guarda fechas en UTC y conviértelas al mostrar.
- **Dinero:** DECIMAL(10,2) o centavos enteros, nunca float.
- **Fuente de verdad:** los totales, estados y permisos se validan siempre en el backend, nunca solo en el cliente.
- **Nunca** debe verse en pantalla "undefined", "null", "NaN" ni pestañas de funciones sin terminar.

## Stack y estructura

Monorepo con dos carpetas:

```
/web      → Laravel (última versión estable), MySQL 8, Sanctum, Blade + Livewire, Tailwind
/mobile   → Ionic + Angular (standalone components), PWA instalable + Capacitor para Android
```

- Backend: Form Requests para validar, Policies para permisos, Resources para respuestas JSON, Services o Actions para la lógica de negocio. Controladores delgados.
- App: servicios de Angular para la API, un interceptor que agrega el token y maneja el 401, y estado con signals.

## Roles

| Rol | Dónde trabaja | Qué puede hacer |
|---|---|---|
| Mesero | App móvil | Ver mesas, tomar comandas, consultar su estado, marcar entregadas, cancelar pendientes y cobrar |
| Cocina | Sitio web (panel de cocina) | Ver órdenes, cambiar su estado y marcar platillos agotados |
| Administrador | Sitio web (panel de administración) | Todo lo administrativo, reportes y corte de caja |

Cada ruta debe estar protegida por rol en el backend (403 si no corresponde).

## Modelo de datos (mínimo)

- `users` (nombre, email, password hash, rol, activo)
- `tables` (número, capacidad, estado: libre / ocupada / por_cobrar)
- `categories`
- `products` (categoría, nombre, descripción, precio, estación: cocina / barra, disponible, soft delete)
- `modifiers` (opciones por platillo, por ejemplo "sin cebolla" o "término medio")
- `orders` (mesa, mesero, estado, total, `idempotency_key` única, motivo de cancelación)
- `order_items` (platillo, cantidad, **precio unitario al momento de la venta**, modificadores elegidos, nota libre)
- `order_status_history` (orden, estado anterior, estado nuevo, usuario, fecha y hora)
- `payments` (orden o mesa, método: efectivo / tarjeta / transferencia, monto, usuario, fecha)
- `cash_closings` (turno, totales por método, efectivo contado, diferencia)
- `audit_logs` (quién, qué acción, sobre qué registro, cuándo; solo inserción)

## Reglas de negocio

- Estados de una orden: **pendiente → en preparación → lista → entregada → cobrada**. No se puede saltar ni regresar un estado.
- El mesero solo puede **cancelar** órdenes pendientes y debe indicar el motivo.
- Una orden cobrada no se puede modificar.
- Al enviar una orden, la mesa pasa a **ocupada**; al cobrar todas sus órdenes, pasa a **libre**.
- Un platillo agotado no se puede agregar a una comanda nueva, pero no afecta las órdenes ya enviadas.
- Si llega dos veces la misma `idempotency_key`, se devuelve la orden existente sin duplicarla.
- Usa transacciones al crear órdenes y al cobrar, y bloquea la mesa (`lockForUpdate`) para evitar conflictos entre dos meseros.
- Cada cambio de estado se registra en `order_status_history`. Cada cancelación, cobro y edición se registra en `audit_logs`.

## Sitio web

**Inicio de sesión:** común para cocina y administración; redirige según el rol. Con límite de intentos.

**Panel de cocina:**
- Tablero de tres columnas (pendiente, en preparación, lista), en orden de llegada.
- Cada tarjeta muestra mesa, mesero, hora, tiempo transcurrido real, platillos, cantidades, modificadores y notas destacadas.
- Botones grandes para cambiar el estado y filtro por estación (cocina o barra).
- Sección para marcar platillos como agotados o disponibles.
- Se actualiza sola por polling, con un indicador de "sin conexión" si falla.

**Panel de administración:**
- Resumen: total recaudado del día, órdenes activas y cerradas, ocupación de mesas, últimas órdenes y últimos pagos.
- CRUD de platillos (con modificadores), categorías, mesas y usuarios.
- Órdenes: listado con filtros por fecha, estado, mesa y mesero, y detalle con su historial de estados.
- Pagos: listado por fecha y método.
- **Corte de caja:** totales del turno por método de pago, captura del efectivo contado y diferencia.
- **Reportes:** ventas por rango de fechas, platillos más vendidos, ventas por método de pago, tiempo promedio de preparación y tiempo total por orden (calculados con `order_status_history`). Exportación a CSV.
- Bitácora de auditoría (solo lectura).

## App móvil del mesero

Navegación inferior con cuatro pestañas: **Mesas, Comanda, Órdenes y Cobro**.

- **Inicio de sesión** con correo y contraseña. La sesión se mantiene y se cierra al recibir un 401.
- **Mesas:** cuadrícula con el estado de cada mesa por color, contadores de libres y ocupadas, y filtros.
- **Comanda:**
  - Muestra la mesa seleccionada; buscador y categorías.
  - Al agregar un platillo se eligen sus modificadores y se escribe una nota.
  - Se ajustan cantidades y se ve el total parcial; el botón "Enviar a cocina" pide confirmación.
  - Los platillos agotados aparecen deshabilitados.
- **Órdenes:** mis órdenes por estado, con aviso visual cuando una está lista; botones para marcar como entregada o cancelar si sigue pendiente (con motivo).
- **Cobro:** elegir una mesa ocupada, ver el detalle y el total calculados por el servidor, elegir el método de pago y confirmar.
- **Sin conexión:** si no hay red, la comanda se guarda en el teléfono (IndexedDB) y se envía sola al volver la señal, con su `idempotency_key`. Muestra claramente cuántas comandas están pendientes de enviar.
- Diseño pensado para usarse con una mano: botones de al menos 44 px y textos legibles en un teléfono de 6 pulgadas.

## Diseño visual

Usa la identidad que ya tiene el restaurante:
- **Colores:** gris pizarra oscuro (#2E3B44) para encabezados, amarillo (#E8C531) como acento principal y rojo (#C0392B) para acciones de salida o cancelación.
- **Fondo:** claro y tarjetas blancas con esquinas redondeadas.
- **Estados con color consistente** en todo el sistema: pendiente (amarillo), en preparación (azul), lista (verde), cancelada (gris).
- **Logo:** déjalo en `/web/public/img/logo.png` y `/mobile/src/assets/logo.png` (yo lo reemplazaré por el real).
- La interfaz debe verse profesional y consistente entre la web y la app.

## Calidad

- **Backend:**
  - Pest con pruebas de cada endpoint y de cada regla de negocio de arriba.
  - Laravel Pint y Larastan.
  - Documentación OpenAPI de la API.
- **App:**
  - Pruebas de los servicios principales.
  - ESLint y Prettier.
- **Extremo a extremo:** una prueba con Playwright del flujo completo (el mesero envía una comanda, cocina la prepara y la marca lista, y el mesero cobra).
- **GitHub Actions:** corre linters y pruebas en cada push.
- **Seeders con datos realistas:**
  - 10 mesas.
  - Un menú típico (por ejemplo bocoles, enchiladas, café de olla y aguas frescas).
  - Un usuario por rol.
  - Órdenes de ejemplo de los últimos días para que los reportes muestren datos.

## Hitos

1. Repositorio, herramientas de calidad, migraciones, modelos, seeders y autenticación por rol.
2. API completa de catálogos, órdenes y cobros, con sus pruebas.
3. App móvil: inicio de sesión, mesas, comanda y envío.
4. Panel de cocina con polling y platillos agotados.
5. App móvil: órdenes, entrega, cancelación y cobro.
6. Panel de administración: resumen, catálogos, órdenes y pagos.
7. Corte de caja, reportes, CSV y bitácora.
8. Modo sin conexión en la app, PWA instalable y build de Android con Capacitor.
9. Prueba de extremo a extremo, revisión final y guía de despliegue en Hostinger (`DEPLOY.md`: SSH, `composer install --no-dev`, migraciones, cachés, cron del scheduler y `.env` de producción).

## Entregable final

- Un `README.md` con los requisitos y comandos para instalar, sembrar datos, levantar la web y la app en local, correr pruebas y desplegar.
- Credenciales de prueba por rol.
- Una lista de verificación con cada funcionalidad de este documento marcada como hecha y la prueba que la cubre.

No des una tarea por terminada si sus pruebas no pasan. Si tienes que tomar una decisión que no está aquí, dime cuál tomaste y por qué.
