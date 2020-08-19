#!/bin/sh

# The docker changes need to be done upstream, see: https://github.com/WordPress/wpdev-docker-images/pull/34

# Make a PHP8 PHPUnit 9 dockerfile.
echo "FROM wordpressdevelop/phpunit:8.0-fpm

# Use PHPUnit 9
RUN curl -sL https://phar.phpunit.de/phpunit-9.phar > /usr/local/bin/phpunit && chmod +x /usr/local/bin/phpunit
" > phpunit9.dockerFile

# Use the PHPUnit 9 dockerfile
sed -i 's!phpunit:$!phpunit:\n    build:\n      context: .\n      dockerfile: phpunit9.dockerFile!i' docker-compose.yml
