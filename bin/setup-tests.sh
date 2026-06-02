#!/usr/bin/env bash
# bin/setup-tests.sh
#
# Run this inside the DDEV container to (re)install the WP test library
# and create the test database. Safe to re-run — all steps are idempotent.
#
# Usage (from plugin root on the host):
#   ddev exec "bash /var/www/html/wp-content/plugins/readmewp/bin/setup-tests.sh"
#
# Or via the helper alias added to .ddev/commands:
#   ddev setup-tests

set -euo pipefail

WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
DB_NAME="wordpress_test"
DB_USER="db"
DB_PASS="db"
DB_HOST="db"

echo "==> Creating test database (if not exists)..."
mysql -uroot -proot -h"${DB_HOST}" \
  -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME};
      GRANT ALL ON ${DB_NAME}.* TO '${DB_USER}'@'%';" 2>/dev/null

echo "==> Downloading WordPress develop tarball..."
curl -sL https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.tar.gz \
  -o /tmp/wp-develop.tar.gz

echo "==> Extracting test library into ${WP_TESTS_DIR}..."
rm -rf "${WP_TESTS_DIR}/includes" "${WP_TESTS_DIR}/data"
mkdir -p "${WP_TESTS_DIR}"
tar --strip-components=3 -xzf /tmp/wp-develop.tar.gz \
  -C "${WP_TESTS_DIR}" \
  "wordpress-develop-trunk/tests/phpunit/includes" \
  "wordpress-develop-trunk/tests/phpunit/data"
rm /tmp/wp-develop.tar.gz

echo "==> Copying wp-tests-config.php..."
cp /var/www/html/wp-content/plugins/readmewp/wp-tests-config.php \
   "${WP_TESTS_DIR}/wp-tests-config.php"

echo "==> Installing Composer dependencies..."
cd /var/www/html/wp-content/plugins/readmewp
composer install --no-interaction --quiet

echo ""
echo "✅ Done. Run tests with:"
echo "   ddev exec \"cd /var/www/html/wp-content/plugins/readmewp && WP_TESTS_DIR=${WP_TESTS_DIR} ./vendor/bin/phpunit --no-coverage\""
