// Compila la PWA para publicarla dentro de Laravel en https://tu-dominio/app
// (mismo dominio que la API, así la URL relativa /api/v1 funciona sin configurar nada).
//
// Uso: npm run build:pwa
import { execSync } from 'node:child_process';
import { cpSync, existsSync, rmSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const target = resolve(root, '../web/public/app');

execSync('npx ng build --configuration production --base-href /app/', { cwd: root, stdio: 'inherit' });

if (existsSync(target)) rmSync(target, { recursive: true, force: true });
cpSync(resolve(root, 'www'), target, { recursive: true });

console.log(`\nPWA copiada a ${target}`);
