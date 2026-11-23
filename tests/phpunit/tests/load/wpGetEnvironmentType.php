<?php

/**
 * Tests for wp_get_environment_type().
 *
 * @group load
 *
 * @covers ::wp_get_environment_type
 */
class Tests_Load_WpGetEnvironmentType extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		putenv( 'WP_ENVIRONMENT_TYPE=' );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_defaults_to_production_when_unset() {
		$this->assertFalse( defined( 'WP_ENVIRONMENT_TYPE' ) );
		$this->assertSame( 'production', wp_get_environment_type() );
	}

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_valid_environment_types
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param string $environment Environment type constant value.
	 */
	public function test_returns_valid_environment_type_from_constant( $environment ) {
		define( 'WP_ENVIRONMENT_TYPE', $environment );

		$this->assertSame( $environment, wp_get_environment_type() );
	}

	/**
	 * Data provider for test_returns_valid_environment_type_from_constant().
	 *
	 * @return array[]
	 */
	public static function data_valid_environment_types(): array {
		return array(
			'local'       => array( 'local' ),
			'development' => array( 'development' ),
			'staging'     => array( 'staging' ),
			'production'  => array( 'production' ),
		);
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_falls_back_to_production_for_invalid_constant() {
		define( 'WP_ENVIRONMENT_TYPE', 'invalid-environment' );

		$this->assertSame( 'production', wp_get_environment_type() );
	}

	/**
	 * @ticket 65819
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_falls_back_to_production_for_empty_constant() {
		define( 'WP_ENVIRONMENT_TYPE', '' );

		$this->assertSame( 'production', wp_get_environment_type() );
	}
}
