<?php

namespace WordPress\Tests\WP_Includes\Functions;

use WP_UnitTestCase;

/**
 * Tests for the `recurse_dirsize()` function.
 *
 * @group functions
 *
 * @covers ::recurse_dirsize
 */
class RecurseDirsizeTest extends WP_UnitTestCase {

	/**
	 * Tests the behavior of the function when the transient doesn't exist.
	 *
	 * @ticket 52241
	 * @ticket 53635
	 */
	public function test_recurse_dirsize_without_transient() {
		delete_transient( 'dirsize_cache' );

		$size = recurse_dirsize( DIR_TESTDATA . '/functions' );

		$this->assertGreaterThan( 10, $size );
	}

	/**
	 * Tests the behavior of the function when the transient does exist, but is not an array.
	 *
	 * In particular, this tests that no PHP TypeErrors are being thrown.
	 *
	 * @ticket 52241
	 * @ticket 53635
	 */
	public function test_recurse_dirsize_with_invalid_transient() {
		set_transient( 'dirsize_cache', 'this is not a valid transient for dirsize cache' );

		$size = recurse_dirsize( DIR_TESTDATA . '/functions' );

		$this->assertGreaterThan( 10, $size );
	}
}