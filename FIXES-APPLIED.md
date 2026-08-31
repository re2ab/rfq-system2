# RFQ-Core 14 — fixes from real install failures

| Error | Cause | Fix in this package |
|-------|--------|---------------------|
| Could not open input file: artisan | Missing Laravel skeleton | `artisan` + `bootstrap/app.php` included |
| Composer security advisories block | audit.block-insecure | `composer.json` config audit.block-insecure false |
| vendor/autoload missing | composer failed | same + install docs |
| could not find driver (mysql) | no pdo_mysql | `.env.example` defaults to **sqlite** for easy test |
| Class Redis not found | CACHE=redis | CACHE_STORE=file in `.env.example` |
| Class Controller not found | missing base controller | `app/Http/Controllers/Controller.php` |
| /login 404 / Install Breeze text | no Breeze | real login in `routes/auth.php` + view |
| Redirect to localhost:8000 | APP_URL / URL generator | AppServiceProvider forceRootUrl when APP_URL is public HTTPS |
| Codespace URL with :8000 | wrong URL | docs: port is inside hostname, do not add :8000 |
| post-autoload-dump artisan fail | artisan missing during first composer | script only runs package:discover if artisan exists |

## Quick start (Codespace / local test)

```bash
composer update --no-interaction --no-audit
cp .env.example .env
touch database/database.sqlite
# set DB_DATABASE to absolute path if needed:
# DB_DATABASE=/full/path/to/database/database.sqlite
php artisan key:generate
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag=permission-migrations
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

Login: admin@example.com / password
