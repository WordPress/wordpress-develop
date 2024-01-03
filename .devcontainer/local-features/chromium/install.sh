#!/usr/bin/env bash

set -eux

export DEBIAN_FRONTEND=noninteractive

echo "Installing Chromium..."
# Copy the welcome message
sudo apt-get update
sudo apt-get -y install --no-install-recommends chromium

echo "Done!"
