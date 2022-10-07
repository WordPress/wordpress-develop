#!/bin/bash

# Install jq alpine package
echo "Installing jq..."
apk add jq

# Extracts the hostname from codespaces configuration
echo "Extracting hostname..."
CODESPACE_NAME=$(jq -r ".CODESPACE_NAME" /workspaces/.codespaces/shared/environment-variables.json)

# Creates wp-config.php file
echo "Creating wp-config.php..."
WP_CONFIG_CODESPACES=".devcontainer/wp-config-codespaces.php"
cp $WP_CONFIG_CODESPACES wp-config.php
sed -i "s/#HOST#/$CODESPACE_NAME/g" wp-config.php

# Install nodejs and npm on the Alpine Linux image
echo "Installing nodejs and npm..."
apk add nodejs npm

# Install npm packages
echo "Installing npm packages..."
npm install

# Run npm build
echo "Running npm build..."
npm run build:dev