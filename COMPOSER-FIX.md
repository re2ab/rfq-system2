# رفع خطای Composer / vendor

## علت
Composer 2.x با `block-insecure` نصب `laravel/framework` را به‌خاطر advisoryهای امنیتی مسدود کرده بود؛ در نتیجه `vendor/` ساخته نشد و `artisan` خطا داد.

پیام Xdebug فقط هشدار است و مهم نیست.

## دستور درست در Codespace (ریشه پروژه)

```bash
cd /workspaces/rfq   # یا مسیر پروژه شما

# روش ۱ (توصیه برای محیط توسعه)
composer config audit.block-insecure false
composer update --no-interaction

# یا یک‌خطی:
composer update --no-interaction --no-audit

# بعد:
cp -n .env.example .env
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

اگر هنوز خطا بود:

```bash
rm -rf vendor composer.lock
composer clear-cache
composer update -W --no-interaction --no-audit
```
