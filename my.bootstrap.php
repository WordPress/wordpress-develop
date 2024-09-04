<?php

// Core stuff.
require_once __DIR__ . '/wp-test-config.php';
wp_cache_init();
require_wp_db();

require_once __DIR__ . '/tests/phpunit/includes/phpunit-adapter-testcase.php';
require_once __DIR__ . '/tests/phpunit/includes/abstract-testcase.php';
require_once __DIR__ . '/tests/phpunit/includes/testcase.php';
require_once __DIR__ . '/tests/phpunit/includes/functions.php';

if ( ! defined( 'DIR_TESTDATA' ) ) {
	define( 'DIR_TESTDATA', __DIR__ . '/tests/phpunit/data' );
}

