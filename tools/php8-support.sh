#!/bin/sh

# The docker changes need to be done upstream, see: https://github.com/WordPress/wpdev-docker-images/pull/34

# Make a PHP8 PHPUnit 9 dockerfile.
echo "FROM wordpressdevelop/phpunit:8.0-fpm

# Use PHPUnit 9
RUN curl -sL https://phar.phpunit.de/phpunit-9.phar > /usr/local/bin/phpunit && chmod +x /usr/local/bin/phpunit
" > php8.dockerFile

# Use the PHP8 + PHPUnit9 dockerfile
sed -i 's!phpunit:$!phpunit:\n    build:\n      context: .\n      dockerfile: php8.dockerFile!i' docker-compose.yml

# these functions definition needs to return void as of PHPUnit8, so we have have our own middleware.
for void_function in setUpBeforeClass setUp assertPreConditions assertPostConditions tearDown tearDownAfterClass onNotSuccessfulTest
do
	echo Converting ${void_function}..
	grep "function\s*${void_function}()\s*{" tests/phpunit/ -rli || echo No affected files.
	grep "function\s*${void_function}()\s*{" tests/phpunit/ -rli | xargs -I% sed -i "s!function\s*${void_function}()!function _${void_function}()!gi" %
	echo
done


# assertContains() no longer handles non-iterables, middleware it as _WPassertContains() fow now.
# This avoids having non-phpunit-related changes in this branch.
grep assertContains tests/phpunit/ -rli | xargs -I% sed -i 's~\$this->assertContains~\$this->_WPassertContains~' %
grep assertNotContains tests/phpunit/ -rli | xargs -I% sed -i 's~\$this->assertNotContains~\$this->_WPassertNotContains~' %

# Output a diff of the modifications for reference.
git diff .

# Lint check the modified files.
git diff --name-only tests/phpunit/ | xargs -I% php -l %