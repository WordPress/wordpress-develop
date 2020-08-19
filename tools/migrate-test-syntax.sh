#!/bin/sh

# these functions definition needs to return void as of PHPUnit8, so we have have our own middleware.
for void_function in setUpBeforeClass setUp assertPreConditions assertPostConditions tearDown tearDownAfterClass onNotSuccessfulTest
do
	echo Converting ${void_function}..
	grep "function\s*${void_function}()\s*{" tests/phpunit/ -rli --exclude-dir=phpunit-compat-traits || echo No affected files.
	grep "function\s*${void_function}()\s*{" tests/phpunit/ -rli --exclude-dir=phpunit-compat-traits | xargs -I% sed -i "s~function\s*${void_function}()~function _${void_function}()~gi" %

	# Convert parent:: calls too, except in abstract-testcase which no longer needs to call it's parent method.
	sed -i "s~parent::${void_function}~// parent::_${void_function}~gi" tests/phpunit/includes/abstract-testcase.php
	grep "parent::${void_function}" tests/phpunit/ -rli --exclude-dir=phpunit-compat-traits | xargs -I% sed -i "s~parent::${void_function}~parent::_${void_function}~gi" %

	echo
done

# assertContains() no longer handles non-iterables, middleware it as _WPassertContains() fow now.
# This avoids having non-phpunit-related changes in this branch.
grep assertContains tests/phpunit/ -rli --exclude-dir=phpunit-compat-traits | xargs -I% sed -i 's~\$this->assertContains~\$this->_WPassertContains~' %
grep assertNotContains tests/phpunit/ -rli --exclude-dir=phpunit-compat-traits | xargs -I% sed -i 's~\$this->assertNotContains~\$this->_WPassertNotContains~' %

# Deprecated functions - Swap for direct replacements, avoids extra noise in PHP 8 tests while testing

# assertFileNotExists() -> assertFileDoesNotExist()
grep assertFileNotExists tests/phpunit/ -rli | xargs -I% sed -i 's~\$this->assertFileNotExists~\$this->assertFileDoesNotExist~' %

# assertRegExp() -> assertMatchesRegularExpression()
# assertNotRegExp() -> assertDoesNotMatchRegularExpression()
grep assertRegExp tests/phpunit/ -rli | xargs -I% sed -i 's~\$this->assertRegExp~\$this->assertMatchesRegularExpression~' %
grep assertNotRegExp tests/phpunit/ -rli | xargs -I% sed -i 's~\$this->assertNotRegExp~\$this->assertDoesNotMatchRegularExpression~' %

# Output a diff of the modifications for reference.
git diff .

# Lint check the modified files.
git diff --name-only tests/phpunit/ | xargs -I% php -l %