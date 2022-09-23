#!/bin/bash
# Install nodejs and npm on the Alpine Linux image
apk add nodejs npm
# Install npm packages
npm install
# Run npm build
npm run build:dev
# Creates the wp-config.php file from docker-compose
docker-compose run --rm cli config create \
    --dbname=wordpress_develop \
    --dbuser=root \
    --dbpass=password \
    --dbhost=mysql \
    --dbprefix=wp_ 