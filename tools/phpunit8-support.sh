#!/bin/sh

# Make a PHP8 PHPUnit 9 dockerfile.
echo "FROM wordpressdevelop/phpunit:7.4-fpm

# Use PHPUnit 9
RUN curl -sL https://phar.phpunit.de/phpunit-8.phar > /usr/local/bin/phpunit && chmod +x /usr/local/bin/phpunit
" > phpunit8.dockerFile

# Use the PHPUnit 8 dockerfile
sed -i 's!phpunit:$!phpunit:\n    build:\n      context: .\n      dockerfile: phpunit8.dockerFile!i' docker-compose.yml
