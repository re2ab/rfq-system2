#!/usr/bin/env bash
set -euo pipefail
cd /workspace

echo "==> RFQ-Core post-create"

# Wait for MySQL
echo "==> Waiting for MySQL..."
for i in $(seq 1 30); do
  if mysqladmin ping -h db -u rfq -prfq_secret --silent 2>/dev/null; then
    echo "MySQL is up."
    break
  fi
  sleep 2
done

# .env
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
  else
    cat > .env <<'ENV'
APP_NAME=RFQ-Core
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=rfq_core
DB_USERNAME=rfq
DB_PASSWORD=rfq_secret
ENV
  fi
fi

# Force Codespace DB settings
sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=mysql/' .env || true
sed -i 's/^DB_HOST=.*/DB_HOST=db/' .env || true
sed -i 's/^DB_PORT=.*/DB_PORT=3306/' .env || true
sed -i 's/^DB_DATABASE=.*/DB_DATABASE=rfq_core/' .env || true
sed -i 's/^DB_USERNAME=.*/DB_USERNAME=rfq/' .env || true
sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=rfq_secret/' .env || true

# Composer (project may be incomplete skeleton — still try)
if [ -f composer.json ]; then
  echo "==> composer install"
  composer config audit.block-insecure false || true
  composer update --no-interaction --prefer-dist --no-audit || composer install --no-interaction --prefer-dist --no-audit || true
fi

# Key
if command -v php >/dev/null && [ -f artisan ]; then
  php artisan key:generate --force || true
fi

# Permissions
mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache storage/app/public storage/app/backups
chmod -R 775 storage bootstrap/cache || true

# Migrate + seed if artisan works
if [ -f artisan ] && [ -d vendor ]; then
  echo "==> migrate --seed"
  php artisan migrate --force || true
  php artisan db:seed --force || true
  php artisan storage:link || true
fi

# Optional front assets
if [ -f package.json ]; then
  npm install || true
  npm run build || true
fi

echo ""
echo "============================================"
echo " RFQ-Core Codespace ready (best-effort)"
echo " Start server:  php artisan serve --host=0.0.0.0 --port=8000"
echo " Login: admin@example.com / password"
echo " Installer: /install/  (if needed)"
echo "============================================"
