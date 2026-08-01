#!/usr/bin/env bash
set -euo pipefail

# rpay database initialization script
# Usage: DB_PASS=your_password ./scripts/init-db.sh
#
# Prerequisites: database and user already created (e.g. via BT Panel / 宝塔面板).
# This script imports schema + seed, then generates syskey and admin password.
# If ROOT_PASS is provided, it can also create the database and user.

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-rpay}"
DB_USER="${DB_USER:-rpay}"
DB_PASS="${DB_PASS:?  set DB_PASS=your_password before running}"
ROOT_PASS="${ROOT_PASS:-}"

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
SQL_DIR="$SCRIPT_DIR/../database"

if [ -n "$ROOT_PASS" ]; then
    echo "==> Creating database and user (using root)..."
    mysql -h"$DB_HOST" -P"$DB_PORT" -uroot -p"$ROOT_PASS" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
SQL
fi

echo "==> Importing schema.sql (29 tables, MySQL 5.6+ compatible)..."
mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_DIR/schema.sql"

echo "==> Importing seed.sql (payment types, plugins, disabled channel templates)..."
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
