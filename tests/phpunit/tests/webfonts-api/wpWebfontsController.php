<?php

/**
 * @group  webfonts
 * @covers WP_Webfonts_Controller
 */
class Tests_Webfonts_API_wpWebfontsController extends WP_UnitTestCase {
	private static $webfonts;

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-registry.php';
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-provider-registry.php';
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-controller.php';
		require_once __DIR__ . '/mocks/class-my-custom-webfonts-provider-mock.php';

		self::$webfonts = self::get_webfonts();
	}

	private function get_controller() {
		$controller = new WP_Webfonts_Controller(
			new WP_Webfonts_Registry(),
			new WP_Webfonts_Provider_Registry()
		);
		$controller->init();

		return $controller;
	}

	/**
	 * @covers WP_Webfonts_Controller::get_webfonts
	 */
	public function test_get_webfonts_when_empty() {
		$controller = $this->get_controller();

		$this->assertSame( array(), $controller->get_webfonts() );
	}

	/**
	 * @covers WP_Webfonts_Controller::register_webfonts
	 */
	public function test_register_webfonts_with_empty_schema() {
		$controller = $this->get_controller();
		$webfonts   = array();

		$controller->register_webfonts( $webfonts );

		$this->assertSame( array(), $controller->get_webfonts() );
	}

	/**
	 * @covers       WP_Webfonts_Controller::register_webfonts
	 * @covers       WP_Webfonts_Controller::register_webfont
	 *
	 * @dataProvider data_register_webfonts_with_invalid_schema
	 *
	 * @param array $webfonts Webfonts input.
	 * @param array $expected Exptected registered webfonts.
	 */
	public function test_register_webfonts_with_invalid_schema( array $webfonts, array $expected ) {
		$controller = $this->get_controller();

		$this->setExpectedIncorrectUsage( 'register_webfonts' );

		$controller->register_webfonts( $webfonts );

		$this->assertSame( $expected, $controller->get_webfonts() );
	}

	/**
	 * Data Provider.
	 *
	 * See Tests_Webfonts_API_wpWebfontsRegistry::data_register_with_invalid_schema()
	 * for more complete test coverage.
	 *
	 * return @array
	 */
	public function data_register_webfonts_with_invalid_schema() {
		return array(
			'provider: not set'        => array(
				'webfonts' => array(
					array(
						'fontFamily' => 'Open Sans',
						'fontStyle'  => 'normal',
						'fontWeight' => '400',
					),
					array(
						'provider'   => 'google',
						'fontFamily' => 'Open Sans',
						'fontStyle'  => 'italic',
						'fontWeight' => '700',
					),
				),
				'expected' => array(
					'open-sans.italic.700' => array(
						'provider'   => 'google',
						'fontFamily' => 'Open Sans',
						'fontStyle'  => 'italic',
						'fontWeight' => '700',
					),
				),
			),
			'font family: invalid key' => array(
				'webfonts' => array(
					array(
						'provider'   => 'google',
						'fontFamily' => 'Roboto',
						'fontStyle'  => 'normal',
						'fontWeight' => '900',
					),
					array(
						'provider'    => 'google',
						'font_family' => 'Open Sans',
						'fontStyle'   => 'normal',
						'fontWeight'  => '400',
					),
				),
				'expected' => array(
					'roboto.normal.900' => array(
						'provider'   => 'google',
						'fontFamily' => 'Roboto',
						'fontStyle'  => 'normal',
						'fontWeight' => '900',
					),
				),
			),
		);
	}

	/**
	 * @covers  WP_Webfonts_Controller::register_webfonts
	 * @covers  WP_Webfonts_Controller::register_webfont
	 */
	public function test_register_webfonts_with_valid_schema() {
		$controller = $this->get_controller();

		$controller->register_webfonts( self::$webfonts );

		$expected = array(
			'open-sans.normal.400' => self::$webfonts[0],
			'open-sans.italic.700' => self::$webfonts[1],
			'roboto.normal.900'    => self::$webfonts[2],
		);
		$this->assertSame( $expected, $controller->get_webfonts() );
	}

	/**
	 * @covers WP_Webfonts_Controller::get_registered_providers
	 */
	public function test_get_registered_providers_core_only() {
		$controller = $this->get_controller();

		$providers = $controller->get_registered_providers();

		$expected = array( 'google', 'local' );
		$this->assertSame( $expected, array_keys( $providers ) );
		$this->assertInstanceOf( 'WP_Webfonts_Google_Provider', $providers['google'] );
		$this->assertInstanceOf( 'WP_Webfonts_Local_Provider', $providers['local'] );
	}

	/**
	 * @covers WP_Webfonts_Controller::register_provider
	 * @covers WP_Webfonts_Controller::get_registered_providers
	 */
	public function test_register_provider() {
		$controller = $this->get_controller();

		$controller->register_provider( My_Custom_Webfonts_Provider_Mock::class );

		$providers = $controller->get_registered_providers();

		$expected = array( 'google', 'local', 'my-custom-provider' );
		$this->assertSame( $expected, array_keys( $providers ) );
	}

	/**
	 * @covers WP_Webfonts_Controller::get_webfonts_by_font_family
	 */
	public function test_get_webfonts_by_font_family() {
		$controller = $this->get_controller();
		$controller->register_webfonts( self::$webfonts );

		$expected = array(
			'roboto.normal.900' => self::$webfonts[2],
		);

		$this->assertSame( $expected, $controller->get_webfonts_by_font_family( 'roboto' ) );
	}

	/**
	 * @covers       WP_Webfonts_Controller::get_webfonts_by_font_family
	 *
	 * @dataProvider data_get_by_font_family_with_invalid_input
	 *
	 * @param mixed Font family input.
	 */
	public function test_get_webfonts_by_font_family_with_invalid_input( $font_family ) {
		$controller = $this->get_controller();
		$controller->register_webfonts( self::$webfonts );

		$this->assertSame( array(), $controller->get_webfonts_by_font_family( $font_family ) );
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

	private static function get_webfonts() {
		return array(
			array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'normal',
				'fontWeight' => '400',
			),
			array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'italic',
				'fontWeight' => '700',
			),
			array(
				'provider'   => 'google',
				'fontFamily' => 'Roboto',
				'fontStyle'  => 'normal',
				'fontWeight' => '900',
			),
		);
	}
}
