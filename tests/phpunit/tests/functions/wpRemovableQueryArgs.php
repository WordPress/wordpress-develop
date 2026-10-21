<?php

/**
 * Tests for the wp_removable_query_args() function.
 *
 * @group functions
 *
 * @covers ::wp_removable_query_args
 */
class Tests_Functions_WpRemovableQueryArgs extends WP_UnitTestCase {

	/**
	 * Tests that the function returns a list of removable query arg names.
	 *
	 * @ticket 65819
	 */
	public function test_returns_default_removable_query_args() {
		$args = wp_removable_query_args();

		$this->assertIsArray( $args );
		$this->assertNotEmpty( $args );
		$this->assertContains( 'settings-updated', $args );
		$this->assertContains( 'message', $args );
		$this->assertContains( 'activated', $args );
		$this->assertContains( 'doing_wp_cron', $args );
		$this->assertContains( 'wp-post-new-reload', $args );
	}

	/**
	 * Tests that the removable_query_args filter can modify the list.
	 *
	 * @ticket 65819
	 */
	public function test_removable_query_args_filter() {
		add_filter(
			'removable_query_args',
			static function ( $args ) {
				$args[] = 'custom_notice';
				return array_values( array_diff( $args, array( 'message' ) ) );
			}
		);

		$args = wp_removable_query_args();

		$this->assertContains( 'custom_notice', $args );
		$this->assertNotContains( 'message', $args );
	}
}
