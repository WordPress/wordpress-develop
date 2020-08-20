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
	echo Converting $1..

	search_replace_raw "\$this->$1" "\$this->$2"
}

# these functions definition needs to return void as of PHPUnit8, which isn't compatible with old PHP.
for void_function in setUpBeforeClass setUp assertPreConditions assertPostConditions tearDown tearDownAfterClass onNotSuccessfulTest
do
	echo Converting ${void_function}..
	search_replace_raw "function\s*${void_function}():\s*void\s*{" "function ${void_function}() {"
done