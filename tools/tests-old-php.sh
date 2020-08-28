#!/bin/bash

function find_matching_files() {
    /usr/bin/env grep "$@" tests/phpunit/ -rli
}

function search_replace() {
	while read -r file; do
		sed -i "s~$1~$2~i" $file
	done < <( find_matching_files $1 )
}

# As of PHPUnit 8, these functions have a void typehint, that's not supported in PHP 5.6-7.0. Strip them out for these versions of PHP.
for void_function in setUpBeforeClass setUp assertPreConditions assertPostConditions tearDown tearDownAfterClass onNotSuccessfulTest
do
	echo Converting ${void_function}..

	search_replace "function\s*${void_function}()\s*:\s*void\s*{" "function ${void_function}() {"
done
