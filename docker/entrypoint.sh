#!/bin/sh
set -e

echo "=== K3 Monitoring System — Docker Entrypoint ==="

# ── 1. Wait for MySQL to be ready ────────────────────────────────────────────
echo "[1/5] Waiting for MySQL..."
until mysqladmin ping -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" --silent 2>/dev/null; do
    sleep 2
done
echo "      MySQL is up."

# ── 2. Generate APP_KEY if not set ───────────────────────────────────────────
echo "[2/5] Checking APP_KEY..."
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    php artisan key:generate --no-interaction
    echo "      APP_KEY generated."
else
    echo "      APP_KEY already set."
fi

# ── 3. Run migrations ─────────────────────────────────────────────────────────
echo "[3/5] Running migrations..."
php artisan migrate --no-interaction --force
echo "      Migrations done."

# ── 4. Seed database (only if users table is empty) ──────────────────────────
echo "[4/5] Seeding database..."
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tail -1)
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
    php artisan db:seed --no-interaction --force
    echo "      Seeding done."
else
    echo "      Database already seeded (${USER_COUNT} users found). Skipping."
fi

# ── 5. Cache config + routes ──────────────────────────────────────────────────
echo "[5/5] Optimizing..."
php artisan config:cache
php artisan route:cache
echo "      Done."

echo ""
echo "================================================="
echo "  App running at http://localhost:8080"
echo ""
echo "  Demo accounts (password: password123):"
echo "    Admin   : admin@k3.com"
echo "    Manager : manager@k3.com"
echo "    HR      : hr@k3.com"
echo ""
echo "  TIF endpoint : POST /api/violations"
echo "  X-Service-Key: check .env TIF_SERVICE_KEY"
echo "================================================="
echo ""

exec "$@"