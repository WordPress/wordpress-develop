#!/usr/bin/env bash

set -eux

export DEBIAN_FRONTEND=noninteractive

# Copy the welcome message
if [ ! -f /usr/local/etc/vscode-dev-containers/first-run-notice.txt ]; then
	echo "Installing First Run Notice..."
	echo -e "👋 Welcome to \"WordPress Core Development\" in Dev Containers!\n\n🛠️  Your environment is fully setup with all the required software.\n\n🚀 To get started, wait for the \"postCreateCommand\" to finish setting things up, then open the portforwarded URL and append '/wp-admin'. Login to the WordPress Dashboard using \`admin/password\` for the credentials.\n" | sudo tee /usr/local/etc/vscode-dev-containers/first-run-notice.txt
fi

echo "Done!"
