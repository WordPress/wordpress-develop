<?php

/**
 * @group option
 */
class Tests_Option_Registration extends WP_UnitTestCase {
	public function test_register() {
		register_setting( 'test_group', 'test_option' );

		$registered = get_registered_settings();
		$this->assertArrayHasKey( 'test_option', $registered );

		$args = $registered['test_option'];
		$this->assertSame( 'test_group', $args['group'] );

		// Check defaults.
		$this->assertSame( 'string', $args['type'] );
		$this->assertFalse( $args['show_in_rest'] );
		$this->assertSame( '', $args['description'] );
	}

	public function test_register_with_callback() {
		register_setting( 'test_group', 'test_option', array( $this, 'filter_registered_setting' ) );

		$filtered = apply_filters( 'sanitize_option_test_option', 'smart', 'test_option', 'smart' );
		$this->assertSame( 'S-M-R-T', $filtered );
	}

	public function test_register_with_array() {
		register_setting(
			'test_group',
			'test_option',
			array(
				'sanitize_callback' => array( $this, 'filter_registered_setting' ),
			)
		);

		$filtered = apply_filters( 'sanitize_option_test_option', 'smart', 'test_option', 'smart' );
		$this->assertSame( 'S-M-R-T', $filtered );
	}

	public function filter_registered_setting() {
		return 'S-M-R-T';
	}

	/**
	 * @ticket 38176
	 */
	public function test_register_with_default() {
		register_setting(
			'test_group',
			'test_default',
			array(
				'default' => 'Got that Viper with them rally stripes',
			)
		);

		$this->assertSame( 'Got that Viper with them rally stripes', get_option( 'test_default' ) );
	}

	/**
	 * @ticket 38176
	 */
	public function test_register_with_default_override() {
		register_setting(
			'test_group',
			'test_default',
			array(
				'default' => 'Got that Viper with them rally stripes',
			)
		);

		// This set of tests/references (and a previous version) are in support of Viper007Bond.
		// His Viper doesn't have rally stripes, but for the sake of the Big Tymers, we'll go with it.
		$this->assertSame( 'We the #1 Stunnas', get_option( 'test_default', 'We the #1 Stunnas' ) );
	}

	/**
	 * @ticket 38930
	 */
	public function test_add_option_with_no_options_cache() {
		register_setting(
			'test_group',
			'test_default',
			array(
				'default' => 'My Default :)',
			)
		);
		wp_cache_delete( 'notoptions', 'options' );
		$this->assertTrue( add_option( 'test_default', 'hello' ) );
		$this->assertSame( 'hello', get_option( 'test_default' ) );
	}

	/**
	 * @expectedDeprecated register_setting
	 */
	public function test_register_deprecated_group_misc() {
		register_setting( 'misc', 'test_option' );
	}

	/**
	 * @expectedDeprecated register_setting
	 */
	public function test_register_deprecated_group_privacy() {
		register_setting( 'privacy', 'test_option' );
	}

	/**
	 * @ticket 43207
	 */
	public function test_unregister_setting_removes_default() {
		register_setting(
			'test_group',
			'test_default',
			array(
				'default' => 'Got that Viper with them rally stripes',
			)
		);

		unregister_setting( 'test_group', 'test_default' );

		$this->assertFalse( has_filter( 'default_option_test_default', 'filter_default_option' ) );
	}
}
