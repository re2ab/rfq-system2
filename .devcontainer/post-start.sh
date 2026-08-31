#!/usr/bin/env bash
# Soft start helper — does not auto-serve (avoids port conflicts)
cd /workspace
if [ -f artisan ] && [ -d vendor ]; then
  echo "[RFQ] Run: php artisan serve --host=0.0.0.0 --port=8000"
fi
