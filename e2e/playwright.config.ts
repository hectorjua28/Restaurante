import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

/**
 * Prueba de extremo a extremo. Levanta Laravel (API + panel de cocina + PWA en /app)
 * con una base SQLite propia, para no tocar los datos de desarrollo.
 *
 * Antes de correr:  cd ../mobile && npm run build:pwa
 * Correr:           npm test
 */
const PORT = 8010;
export const BASE_URL = `http://127.0.0.1:${PORT}`;
const web = resolve(__dirname, '../web');
export const E2E_DB = resolve(web, 'database/e2e.sqlite');

export default defineConfig({
  testDir: './tests',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env['CI'] ? 1 : 0,
  reporter: process.env['CI'] ? [['github'], ['html', { open: 'never' }]] : 'list',
  globalSetup: './global-setup.ts',
  use: {
    baseURL: BASE_URL,
    locale: 'es-MX',
    timezoneId: 'America/Mexico_City',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'flujo-completo', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: `php artisan serve --host=127.0.0.1 --port=${PORT}`,
    cwd: web,
    url: `${BASE_URL}/up`,
    reuseExistingServer: false,
    timeout: 60_000,
    env: {
      APP_ENV: 'local',
      APP_DEBUG: 'true',
      APP_URL: BASE_URL,
      DB_CONNECTION: 'sqlite',
      DB_DATABASE: E2E_DB,
      SESSION_DRIVER: 'file',
      CACHE_STORE: 'file',
      QUEUE_CONNECTION: 'sync',
    },
  },
});
