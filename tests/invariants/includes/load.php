<?php
/**
 * Loads the invariant classes which do not need PHPUnit.
 *
 * The gameshark extension runs this, through gameshark.php, at the start of
 * every PHP process: before PHPUnit and before WordPress.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

require_once __DIR__ . '/class-wp-invariant.php';
require_once __DIR__ . '/class-wp-invariant-call.php';
require_once __DIR__ . '/class-wp-invariant-violations.php';
require_once __DIR__ . '/class-wp-invariant-report.php';
require_once __DIR__ . '/class-wp-invariant-types.php';
require_once __DIR__ . '/class-wp-invariant-types-report.php';
require_once __DIR__ . '/sql/class-wp-invariant-sql-lexer.php';
require_once __DIR__ . '/oracles/class-wp-invariant-browser.php';
require_once __DIR__ . '/oracles/class-wp-invariant-html-api.php';
require_once __DIR__ . '/oracles/class-wp-invariant-mysql.php';

/**
 * Stops PHPUnit unless the gameshark extension has loaded the expected invariants file.
 *
 * Without this, a run without the extension, or with the wrong file, would pass
 * without checking anything.
 *
 * @param string $file The invariants file the run needs, such as tests/invariants/gameshark.php.
 */
function wp_invariants_require_loaded( $file ) {
	$file   = realpath( $file );
	$status = function_exists( 'gameshark_invariants_status' ) ? gameshark_invariants_status() : null;

	if ( PHP_VERSION_ID < 80500 ) {
		$problem = sprintf( 'The invariants need PHP 8.5 or newer, for the parsers they compare WordPress with; this is PHP %s.', PHP_VERSION );
	} elseif ( null === $status ) {
		$problem = 'The gameshark PHP extension is not loaded.';
	} elseif ( ! $status['loaded'] ) {
		$problem = 'The gameshark extension did not load invariants' . ( $status['load_error'] ? ': ' . $status['load_error'] : '.' );
	} elseif ( realpath( (string) $status['file'] ) !== $file ) {
		$problem = sprintf( 'The gameshark extension loaded %s instead.', $status['file'] );
	} else {
		return;
	}

	fwrite(
		STDERR,
		sprintf(
			"%s\nThis run needs GAMESHARK_INVARIANTS=1 and GAMESHARK_INVARIANTS_FILE=%s. See tests/invariants/README.md.\n",
			$problem,
			$file
		)
	);
	exit( 1 );
}
