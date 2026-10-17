<?php

/**
 * @group option
 */
class Tests_Option_SiteTransient extends WP_UnitTestCase {

	/**
	 * Previous `$wpdb->suppress_errors()` state, restored after a test that forces a database error.
	 *
	 * @var bool|null
	 */
	private $suppress_errors = null;

	public function set_up() {
		parent::set_up();

		if ( wp_using_ext_object_cache() ) {
			$this->markTestSkipped( 'This test requires that an external object cache is not in use.' );
		}
	}

	public function tear_down() {
		if ( null !== $this->suppress_errors ) {
			$GLOBALS['wpdb']->suppress_errors( $this->suppress_errors );
			$this->suppress_errors = null;
		}

		parent::tear_down();
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
		// Keep assertEquals() because the objects are intentionally compared by value.
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
	 * Ensure site transients are stored in the options table on single site installations.
	 *
	 * @group ms-excluded
	 *
	 * @covers ::set_site_transient
	 */
	public function test_site_transient_stored_in_options_on_single_site() {
		global $wpdb;
		$key   = 'test_site_transient_stored_in_options_on_single_site';
		$value = 'Test Site Transient Value';

		set_site_transient( $key, $value );

		$option = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_name, option_value from {$wpdb->options} WHERE option_name = %s",
				'_site_transient_' . $key
			)
		);
		// Keep assertEquals() because the database row object is compared by value.
		$this->assertEquals(
			(object) array(
				'option_name'  => '_site_transient_' . $key,
				'option_value' => $value,
			),
			$option,
			'Site transient should be stored in the options table on single site installations.'
		);
	}

	/**
	 * Ensure site transients are stored in the sitemeta table on multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::set_site_transient
	 */
	public function test_site_transients_stored_in_site_meta_on_ms() {
		global $wpdb;
		$key   = 'test_site_transient_stored_in_site_meta_on_ms';
		$value = 'Test Site Transient Value';

		set_site_transient( $key, $value );

		$option = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT meta_key, meta_value from {$wpdb->sitemeta} WHERE meta_key = %s",
				'_site_transient_' . $key
			)
		);
		// Keep assertEquals() because the database row object is compared by value.
		$this->assertEquals(
			(object) array(
				'meta_key'   => '_site_transient_' . $key,
				'meta_value' => $value,
			),
			$option,
			'Site transient should be stored in sitemeta table on multisite.'
		);
	}

	/**
	 * Ensure site transients are not stored in the options table on multisite.
	 *
	 * @group ms-required
	 *
	 * @covers ::set_site_transient
	 */
	public function test_site_transients_not_stored_in_options_table_on_ms() {
		global $wpdb;
		$key   = 'test_site_transients_not_stored_in_options_table_on_ms';
		$value = 'Test Site Transient Value';

		set_site_transient( $key, $value );

		$option = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT option_name, option_value from {$wpdb->options} WHERE option_name = %s",
				'_site_transient_' . $key
			)
		);

		$this->assertNull( $option, 'Querying option table should not return transient on multisite.' );
	}

	/**
	 * Tests that a new site transient is not stored when its timeout cannot be saved.
	 *
	 * @ticket 52345
	 *
	 * @covers ::set_site_transient
	 */
	public function test_set_site_transient_should_not_store_value_when_timeout_cannot_be_added() {
		$key     = 'site_transient_timeout_fails';
		$timeout = "_site_transient_timeout_{$key}";
		$action  = new MockAction();
		add_action( "set_site_transient_{$key}", array( $action, 'action' ) );
		$this->fail_insert_of_site_option( $timeout );

		$this->assertFalse( set_site_transient( $key, 'value', 60 ), 'set_site_transient() should report the failure.' );
		$this->assertFalse( get_site_transient( $key ), 'The site transient should not be readable without a timeout.' );
		$this->assertFalse( get_site_option( "_site_transient_{$key}" ), 'The value should not be stored.' );
		$this->assertSame( 0, $action->get_call_count(), 'The set_site_transient_{$transient} action should not fire.' );
	}

	/**
	 * Tests that a site transient can still be set when an orphaned timeout is left behind.
	 *
	 * @ticket 52345
	 *
	 * @covers ::set_site_transient
	 */
	public function test_set_site_transient_should_store_value_when_orphaned_timeout_exists() {
		$key = 'site_transient_orphaned_timeout';
		set_site_transient( $key, 'old', 60 );
		delete_site_option( "_site_transient_{$key}" );

		$this->assertTrue( set_site_transient( $key, 'new', 60 ), 'set_site_transient() should succeed.' );
		$this->assertSame( 'new', get_site_transient( $key ), 'The new value should be returned.' );
	}

	/**
	 * Tests that a new site transient is stored when another request adds the same timeout first.
	 *
	 * @ticket 52345
	 *
	 * @covers ::set_site_transient
	 */
	public function test_set_site_transient_should_store_value_when_timeout_was_added_concurrently() {
		global $wpdb;

		$key     = 'site_transient_timeout_race';
		$timeout = "_site_transient_timeout_{$key}";
		$filter  = static function ( $query ) use ( $timeout, $wpdb, &$filter ) {
			if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && str_contains( $query, "'{$timeout}'" ) ) {
				remove_filter( 'query', $filter );
				$wpdb->query( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Replays core's already prepared INSERT.
			}
			return $query;
		};
		add_filter( 'query', $filter );

		$this->assertTrue( set_site_transient( $key, 'value', 60 ), 'set_site_transient() should succeed.' );
		$this->assertSame( 'value', get_site_transient( $key ), 'The value should be returned.' );
	}

	/**
	 * Makes the INSERT query for the given site option fail with a database error.
	 *
	 * @param string $option_name Site option name whose INSERT should fail.
	 */
	private function fail_insert_of_site_option( $option_name ) {
		global $wpdb;

		$this->suppress_errors = $wpdb->suppress_errors();
		add_filter(
			'query',
			static function ( $query ) use ( $option_name, $wpdb ) {
				if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && str_contains( $query, "'{$option_name}'" ) ) {
					return str_replace(
						array( "`{$wpdb->options}`", "`{$wpdb->sitemeta}`" ),
						array( "`{$wpdb->options}_missing`", "`{$wpdb->sitemeta}_missing`" ),
						$query
					);
				}
				return $query;
			}
		);
	}
}
