<?php

/**
 * Tests for switch_to_blog() in multisite.
 *
 * @group ms-required
 * @group ms-site
 * @group multisite
 *
 * @covers ::switch_to_blog
 */
class Tests_Multisite_SwitchToBlog extends WP_UnitTestCase {
	protected static $site_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_id = $factory->blog->create();
	}

	public static function wpTearDownAfterClass() {
		wp_delete_site( self::$site_id );
	}

	/**
	 * @ticket 40513
	 */
	public function test_switch_to_blog_accepts_site_object() {
		global $wpdb;

		switch_to_blog( get_site( self::$site_id ) );
		$current_blog_id = get_current_blog_id();
		$wpdb_blog_id    = $wpdb->blogid;
		restore_current_blog();

		$this->assertSame( self::$site_id, $current_blog_id );
		$this->assertSame( self::$site_id, $wpdb_blog_id );
	}

	/**
	 * @ticket 40513
	 */
	public function test_switch_to_blog_with_site_object_passes_int_to_switch_blog_action() {
		$new_ids = array();
		add_action(
			'switch_blog',
			static function ( $new_blog_id ) use ( &$new_ids ) {
				$new_ids[] = $new_blog_id;
			}
		);

		switch_to_blog( get_site( self::$site_id ) );
		restore_current_blog();

		$this->assertSame( self::$site_id, $new_ids[0] );
	}

	/**
	 * @ticket 40513
	 */
	public function test_switch_to_blog_with_current_site_object_takes_same_site_path() {
		$original_id = get_current_blog_id();
		$new_ids     = array();
		add_action(
			'switch_blog',
			static function ( $new_blog_id ) use ( &$new_ids ) {
				$new_ids[] = $new_blog_id;
			}
		);

		switch_to_blog( get_site( $original_id ) );
		$current_blog_id = get_current_blog_id();
		$is_switched     = ms_is_switched();
		restore_current_blog();

		$this->assertSame( $original_id, $current_blog_id );
		$this->assertTrue( $is_switched );
		$this->assertSame( $original_id, $new_ids[0] );
	}

	/**
	 * @ticket 40513
	 */
	public function test_restore_current_blog_after_switching_with_site_object() {
		$original_id = get_current_blog_id();

		switch_to_blog( get_site( self::$site_id ) );
		restore_current_blog();

		$this->assertSame( $original_id, get_current_blog_id() );
		$this->assertSame( array(), $GLOBALS['_wp_switched_stack'] );
	}
}
