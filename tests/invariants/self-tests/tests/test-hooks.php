<?php
/**
 * Self-tests of how invariants hook calls, map arguments, pair checks and record violations.
 *
 * Each test calls the fixtures, which trigger the invariants in ../specs/fixtures.php.
 *
 * @package WordPress
 * @subpackage UnitTests
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Tests_Invariants_Hooks extends TestCase {

	public function test_ids_are_the_spec_file_and_name() {
		$this->assertArrayHasKey( 'fixtures/positive', WP_Invariant::all() );
		$this->assertArrayHasKey( 'fixtures/paired-recursion', WP_Invariant::all() );
	}

	public function test_violation_from_the_code_under_test_is_recorded() {
		wp_invariants_fixture_call( 'wp_invariants_fixture_positive', -1 );

		$violations = WP_Invariant_Violations::in_test( 'fixtures/positive' );
		$this->assertCount( 1, $violations );
		$this->assertSame( 'It was given -1.', $violations[0]['message'] );
		$this->assertSame( 'before', $violations[0]['when'] );
		$this->assertSame( 'wp_invariants_fixture_positive', $violations[0]['target'] );
		$this->assertSame( __METHOD__, $violations[0]['test'] );
		$this->assertStringStartsWith( 'wp_invariants_fixture_call() at tests/invariants/self-tests/fixtures/core.php:', $violations[0]['stack'][0] );
	}

	public function test_call_from_test_code_is_only_checked_when_asked() {
		wp_invariants_fixture_positive( -1 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test( 'fixtures/positive' ) );
		$this->assertCount( 1, WP_Invariant_Violations::in_test( 'fixtures/positive-from-anywhere' ) );
	}

	public function test_nothing_is_recorded_when_invariants_hold() {
		wp_invariants_fixture_call( 'wp_invariants_fixture_positive', 5 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test() );
		$this->assertGreaterThan( 0, $this->runs( 'fixtures/positive#0' ) );
	}

	public function test_arg_gives_the_default_of_an_argument_which_was_not_passed() {
		wp_invariants_fixture_call( 'wp_invariants_fixture_positive', 5 );
		$this->assertSame( array(), WP_Invariant_Violations::in_test( 'fixtures/label-is-default' ) );

		wp_invariants_fixture_call( 'wp_invariants_fixture_positive', 5, 'other' );
		$violations = WP_Invariant_Violations::in_test( 'fixtures/label-is-default' );
		$this->assertCount( 1, $violations );
		$this->assertSame( 'Its label was other.', $violations[0]['message'] );
	}

	public function test_after_check_sees_arguments_as_they_were_when_the_call_started() {
		// The fixture changes its parameter before it returns.
		wp_invariants_fixture_call( 'wp_invariants_fixture_positive', 5 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test( 'fixtures/entry-values' ) );
		$this->assertGreaterThan( 0, $this->runs( 'fixtures/entry-values#0' ) );
	}

	public function test_paired_checks_match_through_recursion() {
		$before = $this->runs( 'fixtures/paired-recursion#1' );
		wp_invariants_fixture_call( 'wp_invariants_fixture_recursive', 3 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test() );
		$this->assertSame( $before + 4, $this->runs( 'fixtures/paired-recursion#1' ) );
	}

	public function test_paired_checks_match_when_a_call_inside_throws() {
		$before = $this->runs( 'fixtures/paired-throw#1' );
		wp_invariants_fixture_call( 'wp_invariants_fixture_throwing', 3 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test() );
		// The innermost call threw, so only the 3 calls around it returned.
		$this->assertSame( $before + 3, $this->runs( 'fixtures/paired-throw#1' ) );
	}

	public function test_instance_method_check_gets_the_object() {
		$fixture = new WP_Invariants_Fixture();
		wp_invariants_fixture_call( array( $fixture, 'add' ), 4 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test() );
		$this->assertGreaterThan( 0, $this->runs( 'fixtures/method-self#0' ) );
	}

	public function test_static_method_check_gets_no_object() {
		wp_invariants_fixture_call( array( 'WP_Invariants_Fixture', 'twice' ), 7 );

		$this->assertSame( array(), WP_Invariant_Violations::in_test() );
		$this->assertGreaterThan( 0, $this->runs( 'fixtures/static-method#0' ) );
	}

	/**
	 * @return int Calls counted over the run so far.
	 */
	public function test_state_counts_the_calls_in_a_test() {
		wp_invariants_fixture_call( 'wp_invariants_fixture_counter' );
		wp_invariants_fixture_call( 'wp_invariants_fixture_counter' );

		$this->assertSame( array( 'calls' => 2 ), WP_Invariant::all()['fixtures/counted-per-test']->current_state() );
		$this->assertSame( 2, WP_Invariant::shared( 'self-test/counter' )->calls );

		$run = WP_Invariant::all()['fixtures/counted-per-run']->current_state();
		$this->assertGreaterThanOrEqual( 2, $run['calls'] );
		return $run['calls'];
	}

	/**
	 * @depends test_state_counts_the_calls_in_a_test
	 *
	 * @param int $run_calls Calls counted over the run by the previous test.
	 */
	public function test_state_starts_again_in_each_test_unless_kept_for_the_run( $run_calls ) {
		$this->assertNull( WP_Invariant::all()['fixtures/counted-per-test']->current_state() );
		$this->assertFalse( isset( WP_Invariant::shared( 'self-test/counter' )->calls ) );

		wp_invariants_fixture_call( 'wp_invariants_fixture_counter' );

		$this->assertSame( array( 'calls' => 1 ), WP_Invariant::all()['fixtures/counted-per-test']->current_state() );
		$this->assertSame( 1, WP_Invariant::shared( 'self-test/counter' )->calls );
		$this->assertSame( array( 'calls' => $run_calls + 1 ), WP_Invariant::all()['fixtures/counted-per-run']->current_state() );
	}

	public function test_checks_see_every_argument_of_a_variadic_function() {
		wp_invariants_fixture_call( 'wp_invariants_fixture_variadic', 1, 'two', array( 3 ) );

		$this->assertSame( array(), WP_Invariant_Violations::in_test() );
		$this->assertGreaterThan( 0, $this->runs( 'fixtures/extra-arguments#1' ) );
	}

	public function test_check_which_throws_is_recorded_without_breaking_the_call() {
		$this->assertSame( 'kept', wp_invariants_fixture_call( 'wp_invariants_fixture_error', 'kept' ) );

		$errors = WP_Invariant_Violations::in_test( 'fixtures/check-throws' );
		$this->assertCount( 1, $errors );
		$this->assertSame( 'hook-error', $errors[0]['kind'] );
		$this->assertStringContainsString( 'RuntimeException: This check always throws.', $errors[0]['message'] );
	}

	/**
	 * Returns how many times a hook has run, as the gameshark extension counts.
	 *
	 * @param string $spec_id Id of the hook, such as `fixtures/positive#0`.
	 * @return int
	 */
	private function runs( $spec_id ) {
		foreach ( gameshark_invariants_status()['specs'] as $spec ) {
			if ( $spec['id'] === $spec_id ) {
				return $spec['executions'];
			}
		}

		$this->fail( "There is no hook {$spec_id}." );
	}
}
