#!/usr/bin/env bash

set -eux

if [ -z "${CODESPACES}" ] ; then
	SITE_HOST="http://localhost:8080"
else
	SITE_HOST="https://${CODESPACE_NAME}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
fi

WP_DIR=/workspaces/wordpress-develop

# Attempt to make ipv4 traffic have a higher priority than ipv6.
sudo sh -c "echo 'precedence ::ffff:0:0/96 100' >> /etc/gai.conf"

# Install Composer dependencies.
cd "${WP_DIR}"
COMPOSER_ALLOW_XDEBUG=0 COMPOSER_MEMORY_LIMIT=-1 composer install

# Install NPM dependencies.
cd "${WP_DIR}"
if [ ! -d "node_modules" ]; then
	npm ci
fi
# Build WordPress Core.
npm run build:dev

# Setup the WordPress environment.
cd "/app"
echo "Setting up WordPress at $SITE_HOST"
#wp db query < "${WP_DIR}"/.devcontainer/mysql-init.sql
wp core install --url="$SITE_HOST" --title="WordPress Trunk" --admin_user="admin" --admin_email="admin@example.com" --admin_password="password" --skip-email

echo "Done!"
