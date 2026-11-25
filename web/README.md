# Antojería Pingüin · Sitio web y API (Laravel)

La documentación completa (instalación, pruebas, credenciales y despliegue) está en el [README principal](../README.md) y en [DEPLOY.md](../DEPLOY.md).

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```
