<?php
/**
 * Test update_home_siteurl().
 *
 * @group admin
 * @group misc
 *
 * @covers ::update_home_siteurl
 */
class Tests_Admin_Includes_Misc_UpdateHomeSiteurl extends WP_UnitTestCase {

	/**
	 * Tests update_home_siteurl() when wp_installing() is true.
	 *
	 * @ticket 65194
	 */
	public function test_update_home_siteurl_should_not_flush_when_installing() {
		wp_installing( true );

		// We use a filter to detect if flush_rewrite_rules is called.
		// Since flush_rewrite_rules calls delete_option( 'rewrite_rules' ) and update_option( 'rewrite_rules' ),
		// we can monitor those, or just check if rewrite_rules are touched.
		// However, flush_rewrite_rules() is a complex function.
		// A simpler way is to check if it returns early by mocking/monitoring.

		$option_updated = false;
		add_filter( 'pre_update_option_rewrite_rules', function( $value ) use ( &$option_updated ) {
			$option_updated = true;
			return $value;
		} );

		update_home_siteurl( 'http://old.example.com', 'http://new.example.com' );

		$this->assertFalse( $option_updated, 'Rewrite rules should not be flushed when installing.' );

		wp_installing( false ); // Reset.
	}

	/**
	 * Tests update_home_siteurl() in single site mode.
	 *
	 * @ticket 65194
	 */
	public function test_update_home_siteurl_should_flush_rules_on_single_site() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'This test is for single site only.' );
		}

		$option_updated = false;
		add_filter( 'pre_update_option_rewrite_rules', function( $value ) use ( &$option_updated ) {
			$option_updated = true;
			return $value;
		} );

		// flush_rewrite_rules() might not update the option if it's already empty or similar.
		// Let's ensure there are rules to flush.
		update_option( 'rewrite_rules', array( 'foo' => 'bar' ) );
		$option_updated = false;

		update_home_siteurl( 'http://old.example.com', 'http://new.example.com' );

		$this->assertTrue( $option_updated, 'Rewrite rules should be flushed on single site.' );
	}

	/**
	 * Tests update_home_siteurl() in multisite mode when NOT switched.
	 *
	 * @group multisite
	 * @ticket 65194
	 */
	public function test_update_home_siteurl_multisite_not_switched() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test is for multisite only.' );
		}

		$option_updated = false;
		add_filter( 'pre_update_option_rewrite_rules', function( $value ) use ( &$option_updated ) {
			$option_updated = true;
			return $value;
		} );

		update_option( 'rewrite_rules', array( 'foo' => 'bar' ) );
		$option_updated = false;

		update_home_siteurl( 'http://old.example.com', 'http://new.example.com' );

		$this->assertTrue( $option_updated, 'Rewrite rules should be flushed in multisite when not switched.' );
	}

	/**
	 * Tests update_home_siteurl() in multisite mode when switched.
	 *
	 * @group multisite
	 * @ticket 65194
	 */
	public function test_update_home_siteurl_multisite_switched() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test is for multisite only.' );
		}

		$blog_id = $this->factory->blog->create();
		switch_to_blog( $blog_id );

		update_option( 'rewrite_rules', array( 'foo' => 'bar' ) );

		update_home_siteurl( 'http://old.example.com', 'http://new.example.com' );

		$this->assertFalse( get_option( 'rewrite_rules' ), 'Rewrite rules option should be deleted when switched in multisite.' );

		restore_current_blog();
	}
}
