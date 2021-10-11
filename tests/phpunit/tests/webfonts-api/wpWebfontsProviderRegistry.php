<?php

/**
 * @group  webfonts
 * @covers WP_Webfonts_Provider_Registry
 */
class Tests_Webfonts_API_wpWebfontsProviderRegistry extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . WPINC . '/webfonts-api/class-wp-webfonts-provider-registry.php';
		require_once __DIR__ . '/mocks/class-my-custom-webfonts-provider-mock.php';
	}

	/**
	 * @covers WP_Webfonts_Provider_Registry::get_registry
	 */
	public function test_get_registry_when_empty() {
		$registry = new WP_Webfonts_Provider_Registry();

		$this->assertSame( array(), $registry->get_registry() );
	}

	/**
	 * @covers WP_Webfonts_Provider_Registry::register
	 * @covers WP_Webfonts_Provider_Registry::get_registry
	 */
	public function test_register_with_invalid_class() {
		$registry = new WP_Webfonts_Provider_Registry();
		$registry->register( 'DoesNotExist' );

		$this->assertSame( array(), $registry->get_registry() );
	}

	/**
	 * @covers WP_Webfonts_Provider_Registry::register
	 * @covers WP_Webfonts_Provider_Registry::get_registry
	 */
	public function test_register_with_valid_class() {
		$registry = new WP_Webfonts_Provider_Registry();
		$registry->register( My_Custom_Webfonts_Provider_Mock::class );

		$providers = $registry->get_registry();

		$this->assertIsArray( $providers );
		$this->assertCount( 1, $providers );
		$this->assertArrayHasKey( 'my-custom-provider', $providers );
		$this->assertInstanceOf( 'My_Custom_Webfonts_Provider_Mock', $providers['my-custom-provider'] );
	}

	/**
	 * @covers WP_Webfonts_Provider_Registry::init
	 * @covers WP_Webfonts_Provider_Registry::get_registry
	 */
	public function test_init() {
		$registry = new WP_Webfonts_Provider_Registry();
		// Register the core providers.
		$registry->init();

		$providers = $registry->get_registry();

		$expected = array( 'google', 'local' );
		$this->assertSame( $expected, array_keys( $providers ) );
		$this->assertInstanceOf( 'WP_Webfonts_Google_Provider', $providers['google'] );
		$this->assertInstanceOf( 'WP_Webfonts_Local_Provider', $providers['local'] );
	}

	/**
	 * @covers WP_Webfonts_Provider_Registry::register
	 * @covers WP_Webfonts_Provider_Registry::get_registry
	 */
	public function test_register_with_core_providers() {
		$registry = new WP_Webfonts_Provider_Registry();
		// Register the core providers.
		$registry->init();
		// Register a custom provider.
		$registry->register( My_Custom_Webfonts_Provider_Mock::class );

		$providers = $registry->get_registry();

		$expected = array( 'google', 'local', 'my-custom-provider' );
		$this->assertSame( $expected, array_keys( $providers ) );
	}

	/**
	 * @covers WP_Webfonts_Provider_Registry::get_preconnect_links
	 *
	 * @dataProvider data_get_preconnect_links
	 *
	 * @param bool   $register_custom When true, registers the custom provider.
	 * @param string $expected        Expected HTML.
	 */
	public function test_get_preconnect_links( $register_custom, $expected ) {
		$registry = new WP_Webfonts_Provider_Registry();
		// Register the core providers.
		$registry->init();
		// Register a custom provider.
		if ( $register_custom ) {
			$registry->register( My_Custom_Webfonts_Provider_Mock::class );
		}

		$this->assertSame( $expected, $registry->get_preconnect_links() );
	}

	/**
	 * Data Provider.
	 *
	 * return @array
	 */
	public function data_get_preconnect_links() {
		return array(
			'core providers'          => array(
				'register_custom' => false,
				'expected'        => <<<LINKS
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">

LINKS
				,
			),
			'core + custom providers' => array(
				'register_custom' => true,
				'expected'        => <<<LINKS
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.my-custom-api.com">

LINKS
				,
			),
		);
	}
}
