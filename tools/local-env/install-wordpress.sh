#!/bin/bash

# Exit if any command fails.
set -e

# Common variables.
DOCKER_COMPOSE_FILE_OPTIONS="-f $(dirname "$0")/docker-compose.yml"
WP_DEBUG=${WP_DEBUG-true}
SCRIPT_DEBUG=${SCRIPT_DEBUG-true}

# Gutenberg script includes.
. "$(dirname "$0")/includes.sh"

# These are the containers and values for the development site.
CLI='cli'
CONTAINER='wordpress'
SITE_TITLE='WordPress Dev'

# Get the host port for the WordPress container.
HOST_PORT=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS port $CONTAINER 80 | awk -F : '{printf $2}')

# Wait until the Docker containers are running and the WordPress site is
# responding to requests.
echo -en $(status_message "Attempting to connect to WordPress...")
until $(curl -L http://localhost:$HOST_PORT -so - 2>&1 | grep -q "WordPress"); do
    echo -n '.'
    sleep 5
done
echo ''

# If this is the test site, we reset the database so no posts/comments/etc.
# dirty up the tests.
if [ "$1" == '--reset-site' ]; then
	echo -e $(status_message "Resetting test database...")
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI db reset --yes --quiet
fi

# Install WordPress.
echo -e $(status_message "Installing WordPress...")
# The `-u 33` flag tells Docker to run the command as a particular user and
# prevents permissions errors. See: https://github.com/WordPress/gutenberg/pull/8427#issuecomment-410232369
docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI core install --title="$SITE_TITLE" --admin_user=admin --admin_password=password --admin_email=test@test.com --skip-email --url=http://localhost:$HOST_PORT --quiet

if [ "$E2E_ROLE" = "author" ]; then
	echo -e $(status_message "Creating an additional author user for testing...")
	# Create an additional author user for testing.
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI user create author author@example.com --role=author --user_pass=authpass --quiet
	# Assign the existing Hello World post to the author.
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI post update 1 --post_author=2 --quiet
fi

CURRENT_WP_VERSION=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run -T --rm $CLI core version)
echo -e $(status_message "Current WordPress version: $CURRENT_WP_VERSION...")

if [ "$WP_VERSION" == "latest" ]; then
	# Check for WordPress updates, to make sure we're running the very latest version.
	echo -e $(status_message "Updating WordPress to the latest version...")
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI core update --quiet
	echo -e $(status_message "Updating The WordPress Database...")
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI core update-db --quiet
fi

# If the 'wordpress' volume wasn't during the down/up earlier, but the post port has changed, we need to update it.
echo -e $(status_message "Checking the site's url...")
CURRENT_URL=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run -T --rm $CLI option get siteurl)
if [ "$CURRENT_URL" != "http://localhost:$HOST_PORT" ]; then
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI option update home "http://localhost:$HOST_PORT" --quiet
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI option update siteurl "http://localhost:$HOST_PORT" --quiet
fi

# Install a dummy favicon to avoid 404 errors.
echo -e $(status_message "Installing a dummy favicon...")
docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm $CONTAINER touch /var/www/html/favicon.ico

# Configure site constants.
echo -e $(status_message "Configuring site constants...")
WP_DEBUG_CURRENT=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run -T --rm -u 33 $CLI config get --type=constant --format=json WP_DEBUG)
if [ $WP_DEBUG != $WP_DEBUG_CURRENT ]; then
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI config set WP_DEBUG $WP_DEBUG --raw --type=constant --quiet
	WP_DEBUG_RESULT=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run -T --rm -u 33 $CLI config get --type=constant --format=json WP_DEBUG)
	echo -e $(status_message "WP_DEBUG: $WP_DEBUG_RESULT...")
fi
SCRIPT_DEBUG_CURRENT=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run -T --rm -u 33 $CLI config get --type=constant --format=json SCRIPT_DEBUG)
if [ $SCRIPT_DEBUG != $SCRIPT_DEBUG_CURRENT ]; then
	docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run --rm -u 33 $CLI config set SCRIPT_DEBUG $SCRIPT_DEBUG --raw --type=constant --quiet
	SCRIPT_DEBUG_RESULT=$(docker-compose $DOCKER_COMPOSE_FILE_OPTIONS run -T --rm -u 33 $CLI config get --type=constant --format=json SCRIPT_DEBUG)
	echo -e $(status_message "SCRIPT_DEBUG: $SCRIPT_DEBUG_RESULT...")
fi
