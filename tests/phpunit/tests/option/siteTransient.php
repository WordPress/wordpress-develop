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
	 * Tests that set_site_transient() rejects names that are not non-empty strings.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_invalid_site_transient_names
	 * @expectedIncorrectUsage set_site_transient
	 *
	 * @covers ::set_site_transient
	 * @covers ::_wp_is_valid_transient_name
	 *
	 * @param mixed $transient Transient name.
	 */
	public function test_set_site_transient_should_reject_invalid_name( $transient ) {
		$this->assertFalse( set_site_transient( $transient, 'value' ), 'set_site_transient() should return false.' );
		$this->assertFalse( get_site_option( '_site_transient_' ), 'No option should be added for an empty transient name.' );
	}

	/**
	 * Tests that get_site_transient() rejects names that are not non-empty strings.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_invalid_site_transient_names
	 * @expectedIncorrectUsage get_site_transient
	 *
	 * @covers ::get_site_transient
	 * @covers ::_wp_is_valid_transient_name
	 *
	 * @param mixed $transient Transient name.
	 */
	public function test_get_site_transient_should_reject_invalid_name( $transient ) {
		$this->assertFalse( get_site_transient( $transient ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_invalid_site_transient_names() {
		return array(
			'empty string'    => array( '' ),
			'whitespace only' => array( '   ' ),
			'null'            => array( null ),
			'boolean false'   => array( false ),
			'boolean true'    => array( true ),
			'integer zero'    => array( 0 ),
			'integer one'     => array( 1 ),
			'empty array'     => array( array() ),
			'object'          => array( new stdClass() ),
		);
	}

	/**
	 * Tests that set_site_transient() accepts a name at the 167 character limit.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_site_transient_names_at_length_limit
	 *
	 * @covers ::set_site_transient
	 * @covers ::get_site_transient
	 * @covers ::delete_site_transient
	 * @covers ::_wp_is_valid_transient_name_length
	 *
	 * @param string $transient Transient name.
	 */
	public function test_set_site_transient_should_accept_name_at_length_limit( $transient ) {
		$this->assertTrue( set_site_transient( $transient, 'value', HOUR_IN_SECONDS ), 'set_site_transient() should return true.' );
		$this->assertNotFalse( get_site_option( '_site_transient_timeout_' . $transient ), 'The timeout option should be stored.' );
		$this->assertSame( 'value', get_site_transient( $transient ), 'get_site_transient() should return the stored value.' );
		$this->assertTrue( delete_site_transient( $transient ), 'delete_site_transient() should return true.' );
		$this->assertFalse( get_site_transient( $transient ), 'get_site_transient() should return false after deletion.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_site_transient_names_at_length_limit() {
		return array(
			'167 single-byte characters' => array( str_repeat( 'a', 167 ) ),
			'167 multibyte characters'   => array( str_repeat( 'é', 167 ) ),
		);
	}

	/**
	 * Tests that set_site_transient() rejects a name over the 167 character limit.
	 *
	 * @ticket 58903
	 *
	 * @dataProvider data_site_transient_names_over_length_limit
	 * @expectedIncorrectUsage set_site_transient
	 *
	 * @covers ::set_site_transient
	 * @covers ::_wp_is_valid_transient_name_length
	 *
	 * @param string $transient Transient name.
	 */
	public function test_set_site_transient_should_reject_name_over_length_limit( $transient ) {
		$this->assertFalse( set_site_transient( $transient, 'value' ), 'set_site_transient() should return false.' );
		$this->assertFalse( get_site_option( '_site_transient_' . $transient ), 'No option should be added.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_site_transient_names_over_length_limit() {
		return array(
			'168 single-byte characters' => array( str_repeat( 'a', 168 ) ),
			'168 multibyte characters'   => array( str_repeat( 'é', 168 ) ),
		);
	}

	/**
	 * Tests that delete_site_transient() can still remove a site transient stored with an empty name.
	 *
	 * @ticket 58903
	 *
	 * @covers ::delete_site_transient
	 */
	public function test_delete_site_transient_should_delete_site_transient_with_empty_name() {
		add_site_option( '_site_transient_', 'value' );

		$this->assertTrue( delete_site_transient( '' ), 'delete_site_transient() should return true.' );
		$this->assertFalse( get_site_option( '_site_transient_' ), 'The option should be deleted.' );
	}
}
