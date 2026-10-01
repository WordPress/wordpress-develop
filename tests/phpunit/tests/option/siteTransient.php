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
	 * Tests that delete_expired_transients() does not delete site transients on another network.
	 *
	 * @ticket 65969
	 *
	 * @group ms-required
	 *
	 * @covers ::delete_expired_transients
	 */
	public function test_delete_expired_transients_does_not_cross_networks() {
		global $wpdb;

		$other_network_id = self::factory()->network->create();
		$key              = 'cross_network_test';
		$value_key        = '_site_transient_' . $key;
		$timeout_key      = '_site_transient_timeout_' . $key;

		// Main network: valid, unexpired transient (1 hour in the future).
		add_site_option( $value_key, 'main_network_value' );
		add_site_option( $timeout_key, time() + HOUR_IN_SECONDS );

		// Other network: same transient name, already expired.
		add_network_option( $other_network_id, $value_key, 'other_network_value' );
		add_network_option( $other_network_id, $timeout_key, time() - 1 );

		delete_expired_transients();

		$main_network_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key IN ( %s, %s )",
				get_current_network_id(),
				$value_key,
				$timeout_key
			)
		);

		$other_network_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key FROM {$wpdb->sitemeta} WHERE site_id = %d AND meta_key IN ( %s, %s )",
				$other_network_id,
				$value_key,
				$timeout_key
			)
		);

		$this->assertContains(
			$value_key,
			$main_network_rows,
			'An unexpired site transient on the main network must not be deleted because one of the same name expired on another network.'
		);
		$this->assertContains(
			$timeout_key,
			$main_network_rows,
			'The timeout row for an unexpired site transient on the main network must remain.'
		);
		$this->assertEmpty(
			$other_network_rows,
			'The expired site transient and timeout on the other network should be deleted.'
		);
	}
}
