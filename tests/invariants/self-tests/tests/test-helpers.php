<?php
/**
 * Self-tests of the helpers for writing invariants.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_Helpers extends TestCase {

	public function test_strings_in_finds_strings_in_nested_arrays_but_not_objects() {
		$object        = new stdClass();
		$object->title = 'hidden';

		$this->assertSame( array( 'a' ), WP_Invariant::strings_in( 'a' ) );
		$this->assertSame( array( 'a', 'b', 'c' ), WP_Invariant::strings_in( array( 'a', array( 'b', 3, array( 'c' ) ), $object ) ) );
		$this->assertSame( array(), WP_Invariant::strings_in( $object ) );
		$this->assertCount( 2, WP_Invariant::strings_in( array( 'a', 'b', 'c' ), 2 ) );
	}

	public function test_depth_is_one_more_for_a_call_a_target_makes() {
		wp_invariants_fixture_call( 'wp_invariants_fixture_outer' );

		$seen = WP_Invariant::shared( 'self-test/depths' )->seen;
		$this->assertSame( $seen['wp_invariants_fixture_outer'] + 1, $seen['wp_invariants_fixture_inner'] );
	}

	public function test_check_on_a_pcre_function_sees_its_error() {
		wp_invariants_fixture_call( 'preg_match', '/(a+)+$/', str_repeat( 'a', 30000 ) . 'b' );
		wp_invariants_fixture_call( 'preg_match', '/a/', 'a' );

		// PHPUnit calls preg_match() too; only these two calls matter.
		$errors = array_values(
			array_filter(
				WP_Invariant::shared( 'self-test/pcre' )->errors,
				static function ( $error ) {
					return in_array( $error[0], array( '/(a+)+$/', '/a/' ), true );
				}
			)
		);
		$this->assertSame( array( array( '/(a+)+$/', PREG_BACKTRACK_LIMIT_ERROR ), array( '/a/', PREG_NO_ERROR ) ), $errors );
	}

	public function test_skipped_spec_files_are_not_loaded() {
		$previous = getenv( 'WP_INVARIANTS_SKIP' );
		putenv( 'WP_INVARIANTS_SKIP=skipped' );
		try {
			$specs = WP_Invariant::load_specs( dirname( __DIR__ ) . '/fixtures/skip-specs' );
		} finally {
			putenv( false === $previous ? 'WP_INVARIANTS_SKIP' : "WP_INVARIANTS_SKIP={$previous}" );
		}

		$this->assertSame( array( 'kept/kept#0' ), array_column( $specs, 'id' ) );
	}
}
