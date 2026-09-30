<?php
/**
 * Tests for the wp_doing_cli() function.
 *
 * @group functions
 * @group load
 *
 * @covers ::wp_doing_cli
 */
class Tests_Functions_WpDoingCli extends WP_UnitTestCase {

	/**
	 * Tests that wp_doing_cli() returns false by default.
	 *
	 * @ticket 65043
	 */
	public function test_wp_doing_cli_returns_false_by_default() {
		$this->assertFalse( wp_doing_cli() );
	}

	/**
	 * Tests that wp_doing_cli() returns true when forced via the filter.
	 *
	 * @ticket 65043
	 */
	public function test_wp_doing_cli_returns_true_when_filtered() {
		add_filter( 'wp_doing_cli', '__return_true' );
		$result = wp_doing_cli();
		remove_filter( 'wp_doing_cli', '__return_true' );

		$this->assertTrue( $result );
	}

	/**
	 * Tests that wp_doing_cli() returns false when forced via the filter.
	 *
	 * @ticket 65043
	 */
	public function test_wp_doing_cli_returns_false_when_filtered() {
		add_filter( 'wp_doing_cli', '__return_false' );
		$result = wp_doing_cli();
		remove_filter( 'wp_doing_cli', '__return_false' );

		$this->assertFalse( $result );
	}

	/**
	 * Tests that wp_doing_cli() applies the wp_doing_cli filter.
	 *
	 * @ticket 65043
	 */
	public function test_wp_doing_cli_applies_filter() {
		$filter_called = false;

		add_filter(
			'wp_doing_cli',
			function ( $doing_cli ) use ( &$filter_called ) {
				$filter_called = true;
				return $doing_cli;
			}
		);

		wp_doing_cli();

		$this->assertTrue( $filter_called, 'The wp_doing_cli filter should be applied.' );
	}
}
