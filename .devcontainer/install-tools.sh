#!/bin/sh

set -eux

echo "Installing wp-cli..."
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
sudo chmod +x wp-cli.phar
sudo mv wp-cli.phar /usr/local/bin/wp

# Copy the welcome message
sudo cp .devcontainer/welcome-message.txt /usr/local/etc/vscode-dev-containers/first-run-notice.txt
SITE_URL="https://${CODESPACE_NAME}-8889.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
sudo sed -i "s!SITE_URL!$SITE_URL!" /usr/local/etc/vscode-dev-containers/first-run-notice.txt
