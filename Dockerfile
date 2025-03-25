FROM wordpressdevelop/php:latest

# Install the FTP extension
RUN docker-php-ext-install ftp