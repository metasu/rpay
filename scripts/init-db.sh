#!/usr/bin/env bash
set -euo pipefail

# rpay database initialization script
# Usage: ./scripts/init-db.sh
# Creates the database, imports schema + seed, generates syskey and admin password.

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-rpay}"
DB_USER="${DB_USER:-rpay}"
DB_PASS="${DB_PASS:?  set DB_PASS=your_password before running}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SQL_DIR="$SCRIPT_DIR/../database"

echo "==> Creating database and user (if not exists)..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e \
  "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;" 2>/dev/null || true

echo "==> Importing schema.sql..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_DIR/schema.sql"

echo "==> Importing seed.sql..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_DIR/seed.sql"

echo "==> Generating syskey and admin credentials..."
SYSKEY="$(openssl rand -hex 32)"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="$(openssl rand -base64 24 | tr -d '\n')"

mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" <<SQL
INSERT INTO pay_config (k, v) VALUES
  ('syskey', '${SYSKEY}'),
  ('admin_user', '${ADMIN_USER}'),
  ('admin_pwd', '${ADMIN_PASS}')
ON DUPLICATE KEY UPDATE v=VALUES(v);
SQL

echo ""
echo "============================================"
echo "  Database initialization complete!"
echo "============================================"
echo ""
echo "  Admin URL:    http://your-domain/admin"
echo "  Admin user:   ${ADMIN_USER}"
echo "  Admin pass:   ${ADMIN_PASS}"
echo "  Syskey:       ${SYSKEY}"
echo ""
echo "  Save these credentials securely."
echo "  Change the admin password after first login."
echo "============================================"
