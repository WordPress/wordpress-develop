#!/bin/sh

# Use the PHPUnit 8 + PHP 7.4 image.
sed -i 's!wordpressdevelop/phpunit:.*!wordpressdevelop/phpunit:8-php-7.4-fpm!' docker-compose.yml
