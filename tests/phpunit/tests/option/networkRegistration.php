<?php

/**
 * Tests specific to registering network options in multisite.
 *
 * @group option
 * @group ms-option
 * @group multisite
 */
class Tests_Network_Registration extends WP_UnitTestCase {
	/**
	 * @ticket 37181
	 * @group ms-required
	 */
	public function test_register() {
		register_network_setting( 'test_option' );

		$registered = get_registered_meta_keys( 'site' );
		$this->assertArrayHasKey( 'test_option', $registered );

		$args = $registered['test_option'];

		// Check defaults.
		$this->assertSame( 'string', $args['type'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertSame( '', $args['description'] );
	}

	/**
	 * @ticket 37181
	 * @group ms-required
	 */
	public function test_register_with_array() {
		register_network_setting(
			'test_option',
			array(
				'sanitize_callback' => array( $this, 'filter_registered_setting' ),
			)
		);

		$filtered = apply_filters( 'sanitize_site_meta_test_option', 'site', 'test_option', 'site' );
		$this->assertSame( 'S-M-R-T', $filtered );
	}

	public function filter_registered_setting() {
		return 'S-M-R-T';
	}

	/**
	 * @ticket 37181
	 * @group ms-required
	 */
	public function test_register_with_default() {
		register_network_setting(
			'test_default',
			array(
				'default' => 'Got that Viper with them rally stripes',
			)
		);

		$this->assertSame( 'Got that Viper with them rally stripes', get_network_option( null, 'test_default' ) );
	}

	/**
	 * @ticket 37181
	 * @group ms-required
	 */
	public function test_register_with_default_override() {
		register_network_setting(
			'test_default',
			array(
				'default' => 'Got that Viper with them rally stripes',
			)
		);

		// This set of tests/references (and a previous version) are in support of Viper007Bond.
		// His Viper doesn't have rally stripes, but for the sake of the Big Tymers, we'll go with it.
		$this->assertSame( 'We the #1 Stunnas', get_network_option( null, 'test_default', 'We the #1 Stunnas' ) );
	}

	/**
	 * @ticket 37181
	 * @group ms-required
	 */
	public function test_add_network_option_with_no_options_cache() {
		register_network_setting(
			'test_default',
			array(
				'default' => 'My Default :)',
			)
		);

		$this->assertTrue( add_network_option( null, 'test_default', 'hello' ) );
		$this->assertSame( 'hello', get_network_option( null, 'test_default' ) );
	}

	/**
	 * @ticket 37181
	 * @group ms-required
	 */
	public function test_unregister_network_setting_removes_default() {
		register_network_setting(
			'test_default',
			array(
				'default' => 'Got that Viper with them rally stripes',
			)
		);

		unregister_network_setting( 'test_default' );

		$this->assertFalse( get_network_option( null, 'test_default' ) );
	}
}
