#!/bin/bash

function find_matching_files() {
    /usr/bin/env grep "$@" tests/phpunit/ -rli --exclude-dir=phpunit-compat-traits
}

function search_replace_raw() {
	while read -r file; do
		sed -i "s~$1~$2~i" $file
	done < <( find_matching_files $1 )
}

function search_replace_assert() {
	search_replace_raw "\$this->$1" "\$this->$2"
}

# these functions definition needs to return void as of PHPUnit8, so we have have our own middleware.
for void_function in setUpBeforeClass setUp assertPreConditions assertPostConditions tearDown tearDownAfterClass onNotSuccessfulTest
do
	echo Converting ${void_function}..
	search_replace_raw "function\s*${void_function}()\s*{" "function _${void_function}() {"
	search_replace_raw "parent::${void_function}" "parent::_${void_function}"

	# abstract-testcase no longer needs to call it's parent methods.
	sed -i "/parent::_${void_function}()/d" tests/phpunit/includes/abstract-testcase.php

	echo
done

# assertContains() no longer handles non-iterables, middleware it as _WPassertContains() fow now.
# This avoids having non-phpunit-related changes in this branch.
search_replace_assert assertContains _WPassertContains
search_replace_assert assertNotContains _WPassertNotContains

# Deprecated functions - Swap for direct replacements, avoids extra noise in PHP 8 tests while testing

# assertFileNotExists() -> assertFileDoesNotExist()
search_replace_assert assertFileNotExists assertFileDoesNotExist

# assertRegExp()    -> assertMatchesRegularExpression()
# assertNotRegExp() -> assertDoesNotMatchRegularExpression()
search_replace_assert assertRegExp assertMatchesRegularExpression
search_replace_assert assertNotRegExp assertDoesNotMatchRegularExpression

# Migrate the assertInternalType() and assertNotInternalType() over
search_replace_assert "assertInternalType(\s*'array'," "assertIsArray("
search_replace_assert "assertInternalType(\s*'boolean'," "assertIsBool("
search_replace_assert "assertInternalType(\s*'bool'," "assertIsBool("
search_replace_assert "assertInternalType(\s*'float'," "assertIsFloat("
search_replace_assert "assertInternalType(\s*'integer'," "assertIsInt("
search_replace_assert "assertInternalType(\s*'int'," "assertIsInt("
search_replace_assert "assertInternalType(\s*'numeric'," "assertIsNumeric("
search_replace_assert "assertInternalType(\s*'object'," "assertIsObject("
search_replace_assert "assertInternalType(\s*'resource'," "assertIsResource("
search_replace_assert "assertInternalType(\s*'string'," "assertIsString("
search_replace_assert "assertInternalType(\s*'scalar'," "assertIsScalar("
search_replace_assert "assertInternalType(\s*'callable'," "assertIsCallable("
search_replace_assert "assertInternalType(\s*'iterable'," "assertIsIterable("
search_replace_assert "assertNotInternalType(\s*'array'," "assertIsNotArray("
search_replace_assert "assertNotInternalType(\s*'boolean'," "assertIsNotBool("
search_replace_assert "assertNotInternalType(\s*'bool'," "assertIsNotBool("
search_replace_assert "assertNotInternalType(\s*'float'," "assertIsNotFloat("
search_replace_assert "assertNotInternalType(\s*'integer'," "assertIsNotInt("
search_replace_assert "assertNotInternalType(\s*'int'," "assertIsNotInt("
search_replace_assert "assertNotInternalType(\s*'numeric'," "assertIsNotNumeric("
search_replace_assert "assertNotInternalType(\s*'object'," "assertIsNotObject("
search_replace_assert "assertNotInternalType(\s*'resource'," "assertIsNotResource("
search_replace_assert "assertNotInternalType(\s*'string'," "assertIsNotString("
search_replace_assert "assertNotInternalType(\s*'scalar'," "assertIsNotScalar("
search_replace_assert "assertNotInternalType(\s*'callable'," "assertIsNotCallable("
search_replace_assert "assertNotInternalType(\s*'iterable'," "assertIsNotIterable("

# Output a diff of the modifications for reference.
git diff .

# Lint check the modified files.
git diff --name-only tests/phpunit/ | xargs -I% php -l %