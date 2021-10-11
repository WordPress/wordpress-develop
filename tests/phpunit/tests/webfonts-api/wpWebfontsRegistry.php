<?php

/**
 * @group  webfonts
 * @covers WP_Webfonts_Registry
 */
class Tests_Webfonts_API_wpWebfontsRegistry extends WP_UnitTestCase {
	private static $webfonts;

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-registry.php';

		self::$webfonts = self::get_webfonts();
	}

	/**
	 * @covers WP_Webfonts_Registry::register
	 *
	 * @dataProvider data_register_with_invalid_schema
	 *
	 * @param array Webfonts input.
	 */
	public function test_register_with_invalid_schema( array $webfont ) {
		$this->setExpectedIncorrectUsage( 'register_webfonts' );

		$registry = new WP_Webfonts_Registry();

		$this->assertSame( '', $registry->register( $webfont ) );
	}

	/**
	 * Data Provider.
	 *
	 * return @array
	 */
	public function data_register_with_invalid_schema() {
		return array(
			'empty array - no schema'   => array(
				array(),
			),
			'provider: not set'         => array(
				array(
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
			),
			'provider: empty string'    => array(
				array(
					'provider'   => '',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
			),
			'provider: invalid type'    => array(
				array(
					'provider'   => null,
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
			),
			'font family: not set'      => array(
				array(
					'provider'   => 'local',
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
			),
			'font family: empty string' => array(
				array(
					'provider'   => 'local',
					'fontFamily' => '',
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
			),
			'font family: invalid type' => array(
				array(
					'provider'   => 'local',
					'fontFamily' => null,
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
			),
			'font style: empty string'  => array(
				array(
					'provider'   => 'local',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => '',
					'fontWeight' => '400',
				),
			),
			'font style: invalid type'  => array(
				array(
					'provider'   => 'local',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => null,
					'fontWeight' => '400',
				),
			),
			'font style: invalid value' => array(
				array(
					'provider'   => 'local',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'invalid',
					'fontWeight' => '400',
				),
			),
			'font wegith: empty string' => array(
				array(
					'provider'   => 'local',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => '',
				),
			),
			'font weight: invalid type' => array(
				array(
					'provider'   => 'local',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => null,
				),
			),
			/* @todo uncomment once value validation is added.
			'font weight: invalid value' => array(
				array(
					'provider'   => 'local',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => 'invalid',
				),
			),
			*/
		);
	}

	/**
	 * @covers WP_Webfonts_Registry::register
	 *
	 * @dataProvider data_register_with_valid_schema
	 *
	 * @param array  Webfonts input.
	 * @param string Expected registration key.
	 */
	public function test_register_with_valid_schema( array $webfont, $expected ) {
		$registry = new WP_Webfonts_Registry();

		$this->assertSame( $expected, $registry->register( $webfont ) );
	}

	/**
	 * Data Provider.
	 *
	 * return @array
	 */
	public function data_register_with_valid_schema() {
		return array(
			'Open Sans; normal; 400' => array(
				'webfonts' => array(
					'provider'   => 'google',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'normal',
					'fontWeight' => '400',
				),
				'expected' => 'open-sans.normal.400',
			),
			'Open Sans; italic; 900' => array(
				'webfonts' => array(
					'provider'   => 'google',
					'fontFamily' => 'Open Sans',
					'fontStyle'  => 'italic',
					'fontWeight' => '900',
				),
				'expected' => 'open-sans.italic.900',
			),
		);
	}

	/**
	 * @covers WP_Webfonts_Registry::get_registry
	 */
	public function test_get_registry() {
		$registry = new WP_Webfonts_Registry();
		$this->register_webfonts( $registry );

		$this->assertSame( self::$webfonts, $registry->get_registry() );
	}

	/**
	 * @covers WP_Webfonts_Registry::get_by_font_family
	 */
	public function test_get_by_font_family() {
		$registry = new WP_Webfonts_Registry();
		$this->register_webfonts( $registry );

		$expected = array(
			'roboto.normal.400' => self::$webfonts['roboto.normal.400'],
			'roboto.normal.900' => self::$webfonts['roboto.normal.900'],
		);
		$this->assertSame( $expected, $registry->get_by_font_family( 'Roboto' ) );
	}

	/**
	 * @covers WP_Webfonts_Registry::get_by_font_family
	 *
	 * @dataProvider data_get_by_font_family_with_invalid_input
	 *
	 * @param mixed Font family input.
	 */
	public function test_get_by_font_family_with_invalid_input( $font_family ) {
		$registry = new WP_Webfonts_Registry();
		$this->register_webfonts( $registry );

		$this->assertSame( array(), $registry->get_by_font_family( $font_family ) );
	}

	/**
	 * Data Provider.
	 *
	 * return @array
	 */
	public function data_get_by_font_family_with_invalid_input() {
		return array(
			'not a string'               => array( true ),
			'empty string'               => array( '' ),
			'font family not registered' => array( 'Does not exist' ),
		);
	}

	/**
	 * Register the webfonts helper function.
	 *
	 * @param WP_Webfonts_Registry $registry Instance of the registry.
	 */
	private function register_webfonts( $registry ) {
		foreach ( self::$webfonts as $webfont ) {
			$registry->register( $webfont );
		}
	}

	/**
	 * Gets the webfonts collection.
	 *
	 * @return string[][]
	 */
	private static function get_webfonts() {
		return array(
			'open-sans.normal.400' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'normal',
				'fontWeight' => '400',
			),
			'open-sans.normal.900' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'normal',
				'fontWeight' => '900',
			),
			'open-sans.italic.400' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'italic',
				'fontWeight' => '400',
			),
			'roboto.normal.400'    => array(
				'provider'   => 'google',
				'fontFamily' => 'Roboto',
				'fontStyle'  => 'normal',
				'fontWeight' => '400',
			),
			'roboto.normal.900'    => array(
				'provider'   => 'google',
				'fontFamily' => 'Roboto',
				'fontStyle'  => 'normal',
				'fontWeight' => '900',
			),
		);
	}
}
