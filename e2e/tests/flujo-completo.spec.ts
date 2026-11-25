import { expect, test, devices, type Page } from '@playwright/test';

/**
 * Flujo completo del restaurante:
 * 1. El mesero (app móvil) toma la comanda de la mesa 10 y la envía a cocina.
 * 2. Cocina (sitio web) la ve en el tablero, la prepara y la marca como lista.
 * 3. Al mesero le llega el aviso, la entrega y cobra la mesa con propina.
 * 4. Administración ve la orden cobrada y el pago.
 */
const PASSWORD = 'pinguin123';
const PLATILLO = 'Enchiladas verdes';
const MODIFICADOR = 'De pollo';
const NOTA = 'Sin cebolla, por favor';

/** Elemento visible dentro de la página activa de Ionic (las otras pestañas quedan ocultas en el DOM). */
const visible = (page: Page, selector: string) => page.locator(`${selector}:visible`);

test('el mesero envía una comanda, cocina la prepara y el mesero cobra', async ({ browser, baseURL }) => {
  // ---------- 1. Mesero: comanda desde la app ----------
  const waiterCtx = await browser.newContext({ ...devices['Pixel 7'], baseURL, locale: 'es-MX', timezoneId: 'America/Mexico_City' });
  const waiter = await waiterCtx.newPage();

  await waiter.goto('/app/');
  await waiter.locator('[data-testid=email] input').fill('mesero@pinguin.test');
  await waiter.locator('[data-testid=password] input').fill(PASSWORD);
  await waiter.locator('[data-testid=login-submit]').click();

  await visible(waiter, '[data-testid=table-10]').click();
  await expect(visible(waiter, '[data-testid=selected-table]')).toHaveText('10');

  await visible(waiter, '[data-testid=search] input').fill('enchiladas verdes');
  await visible(waiter, '.product').filter({ hasText: PLATILLO }).click();

  const modal = waiter.locator('ion-modal');
  await modal.locator('ion-checkbox', { hasText: MODIFICADOR }).click();
  await modal.locator('[aria-label="Más"]').click();
  await modal.locator('[data-testid=option-note] textarea').fill(NOTA);
  // Enchiladas verdes $85 + De pollo $15 = $100 × 2
  await expect(modal.locator('[data-testid=option-add]')).toContainText('$200.00');
  await modal.locator('[data-testid=option-add]').click();

  await expect(visible(waiter, '[data-testid=draft-total]')).toHaveText('$200.00');
  await visible(waiter, '[data-testid=send-order]').click();
  await waiter.locator('ion-alert button', { hasText: 'Enviar' }).click();

  const sentToast = waiter.locator('ion-toast').filter({ hasText: 'enviada a cocina' });
  await expect(sentToast).toBeAttached();
  const orderId = Number(/#(\d+)/.exec((await sentToast.evaluate((t) => (t as unknown as { message: string }).message)) ?? '')?.[1]);
  expect(orderId).toBeGreaterThan(0);

  // ---------- 2. Cocina: prepara y marca lista ----------
  const kitchenCtx = await browser.newContext({ baseURL, locale: 'es-MX', timezoneId: 'America/Mexico_City' });
  const kitchen = await kitchenCtx.newPage();
  await kitchen.goto('/login');
  await kitchen.fill('#email', 'cocina@pinguin.test');
  await kitchen.fill('#password', PASSWORD);
  await kitchen.click('button[type=submit]');
  await expect(kitchen).toHaveURL(/\/cocina$/);

  const card = kitchen.locator(`[data-testid=order-${orderId}]`);
  await expect(card).toContainText('Mesa 10');
  await expect(card).toContainText(PLATILLO);
  await expect(card).toContainText(MODIFICADOR);
  await expect(card).toContainText(`Nota: ${NOTA}`);
  await expect(card).toContainText('2×');

  await card.getByRole('button', { name: 'Empezar a preparar' }).click();
  await expect(kitchen.locator('section', { hasText: 'En preparación' }).locator(`[data-testid=order-${orderId}]`)).toBeVisible();

  await kitchen.locator(`[data-testid=order-${orderId}]`).getByRole('button', { name: 'Marcar como lista' }).click();
  await expect(kitchen.locator(`[data-testid=order-${orderId}]`)).toContainText('Esperando a que el mesero la entregue');

  // ---------- 3. Mesero: aviso de lista, entrega y cobro ----------
  await expect(waiter.locator('[data-testid=ready-badge]')).toBeVisible({ timeout: 20_000 });
  await waiter.locator('[data-testid=tab-ordenes]').click();
  await visible(waiter, `[data-testid=deliver-${orderId}]`).click();
  await visible(waiter, `[data-testid=charge-${orderId}]`).click();

  await expect(visible(waiter, '[data-testid=bill-total]')).toHaveText('$200.00');
  await visible(waiter, '[data-testid=tip-0] input').fill('30');
  await expect(visible(waiter, '[data-testid=confirm-charge]')).toBeEnabled();
  await visible(waiter, '[data-testid=confirm-charge]').click();
  await waiter.locator('ion-alert button', { hasText: 'Cobrar' }).click();
  await expect(waiter.locator('ion-toast').filter({ hasText: 'Mesa 10 cobrada' })).toBeAttached();

  // La cocina ya no la muestra.
  await expect(kitchen.locator(`[data-testid=order-${orderId}]`)).toHaveCount(0, { timeout: 15_000 });

  // ---------- 4. Administración: la orden aparece cobrada con su pago ----------
  const adminCtx = await browser.newContext({ baseURL, locale: 'es-MX', timezoneId: 'America/Mexico_City' });
  const admin = await adminCtx.newPage();
  await admin.goto('/login');
  await admin.fill('#email', 'admin@pinguin.test');
  await admin.fill('#password', PASSWORD);
  await admin.click('button[type=submit]');
  await admin.goto(`/admin/ordenes/${orderId}`);
  await expect(admin.getByText(`Orden #${orderId}`)).toBeVisible();
  await expect(admin.locator('main')).toContainText('Cobrada');
  await expect(admin.locator('main')).toContainText('$200.00');
  await expect(admin.locator('main')).toContainText('+$30.00');

  // En ninguna pantalla se debe ver "undefined", "null" ni "NaN".
  for (const page of [waiter, kitchen, admin]) {
    await expect(page.locator('body')).not.toContainText(/\b(undefined|null|NaN)\b/);
  }

  await Promise.all([waiterCtx.close(), kitchenCtx.close(), adminCtx.close()]);
});
