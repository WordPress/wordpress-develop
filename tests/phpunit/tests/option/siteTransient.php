<?php

/**
 * @group option
 */
class Tests_Option_SiteTransient extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}
	}

	/**
	 * @covers ::get_site_transient
	 * @covers ::set_site_transient
	 * @covers ::delete_site_transient
	 */
	public function test_the_basics() {
		$key    = 'key1';
		$value  = 'value1';
		$value2 = 'value2';

		$this->assertFalse( get_site_transient( 'doesnotexist' ) );
		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertSame( $value, get_site_transient( $key ) );
		$this->assertFalse( set_site_transient( $key, $value ) );
		$this->assertTrue( set_site_transient( $key, $value2 ) );
		$this->assertSame( $value2, get_site_transient( $key ) );
		$this->assertTrue( delete_site_transient( $key ) );
		$this->assertFalse( get_site_transient( $key ) );
		$this->assertFalse( delete_site_transient( $key ) );
	}

	/**
	 * @covers ::get_site_transient
	 * @covers ::set_site_transient
	 * @covers ::delete_site_transient
	 */
	public function test_serialized_data() {
		$key   = __FUNCTION__;
		$value = array(
			'foo' => true,
			'bar' => true,
		);

		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertSame( $value, get_site_transient( $key ) );

		$value = (object) $value;
		$this->assertTrue( set_site_transient( $key, $value ) );
		$this->assertEquals( $value, get_site_transient( $key ) );
		$this->assertTrue( delete_site_transient( $key ) );
	}

	/**
	 * @ticket 22846
	 * @group ms-excluded
	 *
	 * @covers ::set_site_transient
	 * @covers ::wp_load_alloptions
	 */
	public function test_set_site_transient_is_not_stored_as_autoload_option() {
		$key = 'not_autoloaded';

		set_site_transient( $key, 'Not an autoload option' );

		$options = wp_load_alloptions();

		$this->assertArrayNotHasKey( '_site_transient_' . $key, $options );
	}

	/**
	 * @ticket 63719
	 *
	 * @covers fetch_feed
	 */
	public function test_feed_cache_transient_uses_site_transients() {
		// Fetch the WordPress.org news feed - this should create site transients
		$feed                     = fetch_feed( 'https://wordpress.org/news/feed/' );
		$feed_hash                = md5( 'https://wordpress.org/news/feed/' );
		$feed_transient_value     = get_site_transient( 'feed_' . $feed_hash );
		$feed_mod_transient_value = get_site_transient( 'feed_mod_' . $feed_hash );

		$this->assertNotFalse( $feed_transient_value, 'Feed transient should be stored as site transient' );
		$this->assertNotFalse( $feed_mod_transient_value, 'Feed mod transient should be stored as site transient' );

		// Verify the correct database table is used based on site type
		if ( is_multisite() ) {
			// In multisite, site transients should be stored in wp_sitemeta
			$site_transient = get_site_option( '_site_transient_feed_' . $feed_hash );
			$this->assertNotFalse( $site_transient, 'Feed transient should be stored in wp_sitemeta for multisite' );
		} else {
			// In single site, site transients should be stored in wp_options
			// the option name is _site_transient_feed_<hash> since we are using the site transient API
			$site_transient = get_option( '_site_transient_feed_' . $feed_hash );
			$this->assertNotFalse( $site_transient, 'Feed transient should be stored in wp_options for single site' );
		}
	}
}
