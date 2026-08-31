# Codespaces – RFQ-Core

## اگر Recovery Mode آمد

1. این فایل‌ها را در ریشه ریپو داشته باشید (پوشه `.devcontainer`).
2. در VS Code / Codespaces:
   - `Ctrl+Shift+P` → **Codespaces: Rebuild Container**
3. اگر باز هم خطا بود:
   - `Ctrl+Shift+P` → **Codespaces: Rebuild Container** و گزینه **Full Rebuild** را بزنید.

## بعد از بالا آمدن

```bash
composer install
cp -n .env.example .env
# برای تست سریع بدون MySQL:
# در .env بگذارید:
# DB_CONNECTION=sqlite
# # DB_DATABASE را خالی بگذارید یا:
# DB_DATABASE=/workspaces/<repo>/database/database.sqlite
touch database/database.sqlite
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan serve --host=0.0.0.0 --port=8000
```

سپس تب **Ports** → پورت 8000 → Open in Browser.

## نکته

تنظیم قبلی با `docker-compose` + MySQL گاهی در Codespaces به Recovery می‌رود.
نسخه فعلی فقط یک image PHP رسمی است و پایدارتر است. دیتابیس پیش‌فرض می‌تواند SQLite باشد.
