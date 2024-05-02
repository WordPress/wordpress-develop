#!/bin/sh

set -eux

if [ -z ${CODESPACE_NAME+x} ]; then
	export LOCAL_URL="http://localhost:8080"
else
	export LOCAL_URL="https://${CODESPACE_NAME}-8889.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN}"
fi

# Install dependencies
npm install && npm run build:dev

# Install WordPress and activate the plugin/theme.
echo "Setting up WordPress at $LOCAL_URL"
npm run env:install
