<?php

/**
 * @group option
 */
class Tests_Option_WpSetOptionAutoload extends WP_UnitTestCase {
	/**
	 * @ticket 58964
	 *
	 * @covers ::wp_set_option_autoload
	 */
	public function test_autoload_should_set_yes() {
		global $wpdb;

		add_option( 'foo', 'bar', '', 'no' );

		$updated = wp_set_option_autoload( 'foo', 'yes' );
		$this->assertTrue( $updated );

		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = $wpdb->num_queries;
		$value  = get_option( 'foo' );

		$this->assertSame( $before, $wpdb->num_queries );
	}

	public function test_autoload_should_set_no() {
		global $wpdb;

		add_option( 'foo', 'bar', '', 'yes' );

		$updated = wp_set_option_autoload( 'foo', 'no' );
		$this->assertTrue( $updated );

		$this->flush_cache();

		// Populate the alloptions cache, which includes autoload=yes options.
		wp_load_alloptions();

		$before = $wpdb->num_queries;
		$value  = get_option( 'foo' );

		$this->assertSame( $before + 1, $wpdb->num_queries );
	}
}
