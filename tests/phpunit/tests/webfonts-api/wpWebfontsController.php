<?php

/**
 * @group  webfonts
 * @covers WP_Webfonts_Controller
 */
class Tests_Webfonts_API_wpWebfontsController extends WP_UnitTestCase {
	private $controller;
	private $webfont_registry_mock;
	private $provider_registry_mock;

	public static function set_up_before_class() {
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-registry.php';
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-provider-registry.php';
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-controller.php';
		require_once __DIR__ . '/mocks/class-my-custom-webfonts-provider-mock.php';
	}

	public function set_up() {
		parent::set_up();

		$this->webfont_registry_mock  = $this->getMockBuilder( 'WP_Webfonts_Registry' )
											->disableOriginalConstructor()
											->getMock();
		$this->provider_registry_mock = $this->getMockBuilder( 'WP_Webfonts_Provider_Registry' )
											->getMock();
		$this->controller             = new WP_Webfonts_Controller(
			$this->webfont_registry_mock,
			$this->provider_registry_mock
		);
	}

	/**
	 * @covers WP_Webfonts_Controller::init
	 *
	 * @dataProvider data_init
	 *
	 * @param string $hook       Expected hook name.
	 * @param bool   $did_action Whether the action fired or not.
	 */
	public function test_init( $hook, $did_action ) {
		$this->provider_registry_mock
			->expects( $this->once() )
			->method( 'init' );

		if ( $did_action ) {
			do_action( 'wp_enqueue_scripts' );
		}

		$this->controller->init();

		$this->assertSame(
			10,
			has_action( $hook, array( $this->controller, 'generate_and_enqueue_styles' ) )
		);
		$this->assertSame(
			10,
			has_action( 'admin_init', array( $this->controller, 'generate_and_enqueue_editor_styles' ) )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_init() {
		return array(
			'did_action fired'        => array(
				'hook'       => 'wp_print_footer_scripts',
				'did_action' => true,
			),
			'did_action did not fire' => array(
				'hook'       => 'wp_enqueue_scripts',
				'did_action' => false,
			),
		);
	}

	/**
	 * @covers WP_Webfonts_Controller::register_webfonts
	 */
	public function test_register_webfonts_with_empty_schema() {
		$webfonts = array();

		$this->webfont_registry_mock
			->expects( $this->never() )
			->method( 'register' );

		$this->controller->register_webfonts( $webfonts );
	}

	/**
	 * @covers WP_Webfonts_Controller::register_webfonts
	 * @covers WP_Webfonts_Controller::register_webfont
	 */
	public function test_register_webfonts() {
		$webfonts = array(
			'open-sans.normal.400' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'normal',
				'fontWeight' => '400',
			),
			'open-sans.italic.700' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'italic',
				'fontWeight' => '700',
			),
			'roboto.normal.900'    => array(
				'provider'   => 'google',
				'fontFamily' => 'Roboto',
				'fontStyle'  => 'normal',
				'fontWeight' => '900',
			),
		);

		$this->mock_register_webfonts( $webfonts );

		$this->controller->register_webfonts( $webfonts );
	}

	/**
	 * @covers WP_Webfonts_Controller::get_webfonts
	 */
	public function test_get_webfonts() {
		$expected = array(
			'open-sans.normal.400' => array(
				'provider'    => 'google',
				'font-family' => 'Open Sans',
				'font-style'  => 'normal',
				'font-weight' => '400',
			),
			'open-sans.italic.700' => array(
				'provider'    => 'google',
				'font-family' => 'Open Sans',
				'font-style'  => 'italic',
				'fontWeight'  => '700',
			),
			'roboto.normal.900'    => array(
				'provider'    => 'google',
				'font-family' => 'Roboto',
				'font-style'  => 'normal',
				'font-weight' => '900',
			),
		);

		$this->webfont_registry_mock
			->expects( $this->once() )
			->method( 'get_registry' )
			->willReturn( $expected );

		$this->assertSame( $expected, $this->controller->get_webfonts() );
	}

	/**
	 * @covers WP_Webfonts_Controller::get_webfonts_by_provider
	 */
	public function test_get_webfonts_by_provider() {
		$provider_id = 'google';
		$expected    = array(
			'open-sans.normal.400' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'normal',
				'fontWeight' => '400',
			),
			'open-sans.italic.700' => array(
				'provider'   => 'google',
				'fontFamily' => 'Open Sans',
				'fontStyle'  => 'italic',
				'fontWeight' => '700',
			),
			'roboto.normal.900'    => array(
				'provider'   => 'google',
				'fontFamily' => 'Roboto',
				'fontStyle'  => 'normal',
				'fontWeight' => '900',
			),
		);

		$this->webfont_registry_mock
			->expects( $this->once() )
			->method( 'get_by_provider' )
			->with( $this->equalTo( $provider_id ) )
			->willReturn( $expected );

		$this->assertSame( $expected, $this->controller->get_webfonts_by_provider( $provider_id ) );
	}

	/**
	 * @covers WP_Webfonts_Controller::get_webfonts_by_font_family
	 */
	public function test_get_webfonts_by_font_family() {
		$font_family = 'roboto';
		$expected    = array(
			'roboto.normal.900' => array(
				'provider'    => 'google',
				'font-family' => 'Roboto',
				'font-style'  => 'normal',
				'font-weight' => '900',
			),
		);

		$this->webfont_registry_mock
			->expects( $this->once() )
			->method( 'get_by_font_family' )
			->with( $this->equalTo( $font_family ) )
			->willReturn( $expected );

		$this->assertSame( $expected, $this->controller->get_webfonts_by_font_family( $font_family ) );
	}

	/**
	 * @covers WP_Webfonts_Controller::get_registered_providers
	 */
	public function test_get_registered_providers() {
		$expected = array(
			'my-custom-provider' => new My_Custom_Webfonts_Provider_Mock(),
		);

		$this->provider_registry_mock
			->expects( $this->once() )
			->method( 'get_registry' )
			->willReturn( $expected );

		$actual = $this->controller->get_registered_providers();

		$this->assertIsArray( $actual );
		$this->assertCount( 1, $actual );
		$this->assertArrayHasKey( 'my-custom-provider', $actual );
		$this->assertInstanceOf( 'My_Custom_Webfonts_Provider_Mock', $actual['my-custom-provider'] );
	}

	/**
	 * @covers WP_Webfonts_Controller::register_provider
	 */
	public function test_register_provider() {
		$classname = My_Custom_Webfonts_Provider_Mock::class;

		$this->provider_registry_mock
			->expects( $this->once() )
			->method( 'register' )
			->with( $this->equalTo( $classname ) )
			->willReturn( true );

		$this->assertTrue( $this->controller->register_provider( $classname ) );
	}

	/**
	 * @covers WP_Webfonts_Controller::generate_and_enqueue_styles
	 * @covers WP_Webfonts_Controller::generate_and_enqueue_editor_styles
	 *
	 * @dataProvider data_generate_and_enqueue_editor_styles
	 *
	 * @param string $stylestyle_handle Handle for the registered stylesheet.
	 */
	public function test_generate_and_enqueue_editor_styles( $stylestyle_handle ) {
		/*
		 * Set the stylesheet_handle property.
		 * This is set in WP_Webfonts_Controller::init(); however, init is not part
		 * of this test (as it has its own test).
		 */
		$property = new ReflectionProperty( $this->controller, 'stylesheet_handle' );
		$property->setAccessible( true );
		$property->setValue( $this->controller, $stylestyle_handle );

		// Set up the provider mock.
		$provider  = new My_Custom_Webfonts_Provider_Mock();
		$providers = array(
			'my-custom-provider' => $provider,
		);
		$this->provider_registry_mock
			->expects( $this->once() )
			->method( 'get_registry' )
			->willReturn( $providers );

		// Set up the webfonts registry mock.
		$webfonts = array(
			'source-serif-pro.normal.200 900' => array(
				'provider'    => 'my-custom-provider',
				'font-family' => 'Source Serif Pro',
				'font-style'  => 'normal',
				'font-weight' => '200 900',
			),
			'source-serif-pro.italic.200 900' => array(
				'provider'    => 'my-custom-provider',
				'font-family' => 'Source Serif Pro',
				'font-style'  => 'italic',
				'font-weight' => '200 900',
			),
		);
		$this->webfont_registry_mock
			->expects( $this->once() )
			->method( 'get_by_provider' )
			->with( $this->equalTo( 'my-custom-provider' ) )
			->willReturn( $webfonts );

		// Fire the method being tested.
		$this->controller->generate_and_enqueue_styles();

		/*
		 * As this method adds an inline style, the test needs to print it.
		 * Print the webfont styles and test the output matches expectation.
		 */
		$expected  = "<style id='{$stylestyle_handle}-inline-css' type='text/css'>\n";
		$expected .= $provider->get_css() . "\n";
		$expected .= "</style>\n";
		$this->expectOutputString( $expected );
		wp_print_styles( $stylestyle_handle );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_generate_and_enqueue_editor_styles() {
		return array(
			'for wp_enqueue_scripts'      => array( 'webfonts' ),
			'for wp_print_footer_scripts' => array( 'webfonts-footer' ),
		);
	}

	/**
	 * Mocks WP_Webfonts_Provider::register().
	 *
	 * @since 5.9.0
	 *
	 * @param array $webfonts Webfonts to register.
	 */
	private function mock_register_webfonts( array $webfonts ) {
		$register_webfonts = array();
		foreach ( $webfonts as $webfont ) {
			$register_webfonts[] = array( $this->equalTo( $webfont ) );
		}

		$this->webfont_registry_mock
			->expects( $this->exactly( count( $webfonts ) ) )
			->method( 'register' )
			->withConsecutive( ...$register_webfonts );
	}
}
