import { execSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

/** Base SQLite limpia con los datos de ejemplo y verificación de que la PWA esté compilada. */
export default function globalSetup(): void {
  const web = resolve(__dirname, '../web');
  const db = resolve(web, 'database/e2e.sqlite');

  if (!existsSync(resolve(web, 'public/app/index.html'))) {
    throw new Error('La PWA no está compilada. Ejecuta primero: cd mobile && npm run build:pwa');
  }
  if (!existsSync(resolve(web, 'public/build/manifest.json'))) {
    throw new Error('Faltan los assets del sitio. Ejecuta primero: cd web && npm run build');
  }

  rmSync(db, { force: true });
  writeFileSync(db, '');
  execSync('php artisan migrate:fresh --seed --force', {
    cwd: web,
    stdio: 'inherit',
    env: { ...process.env, DB_CONNECTION: 'sqlite', DB_DATABASE: db, APP_ENV: 'local' },
  });
}
