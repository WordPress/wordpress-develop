<?php
/**
 * Bootstrap for leak detection runs, see tests/phpunit/README.md.
 */

// Before the core bootstrap, which reinstalls the test database. PHPUnit 5.7 has this interface as an alias.
if ( ! interface_exists( 'PHPUnit\Framework\TestListener' ) ) {
	fwrite( STDERR, "Leak detection needs PHPUnit 9 or earlier: PHPUnit 10 removed test listeners.\n" );
	exit( 1 );
}

if ( ! getenv( 'WP_TESTS_LEAKS_OUT' ) ) {
	putenv( 'WP_TESTS_LEAKS_OUT=' . dirname( __DIR__ ) . '/build/leaks/snapshots.jsonl' );
}

// Before the core bootstrap, which can exit: a failed run, or one matching no test, must not leave the last run's snapshots.
if ( ! isset( $GLOBALS['__PHPUNIT_BOOTSTRAP'] ) ) {
	$leaks_out = getenv( 'WP_TESTS_LEAKS_OUT' );
	if ( ! is_dir( dirname( $leaks_out ) ) ) {
		mkdir( dirname( $leaks_out ), 0777, true );
	}

	if ( false === file_put_contents( $leaks_out, '' ) ) {
		fwrite( STDERR, "Cannot write $leaks_out.\n" );
		exit( 1 );
	}
}

require dirname( __DIR__ ) . '/includes/bootstrap.php';
require_once __DIR__ . '/class-wp-phpunit-leak-listener.php';

// A process-isolated test's child process loads this bootstrap but no listeners.
if ( isset( $GLOBALS['__PHPUNIT_BOOTSTRAP'] ) ) {
	( new WP_PHPUnit_Leak_Listener() )->watch_child();
}
