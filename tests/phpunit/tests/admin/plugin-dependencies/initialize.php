<?php
/**
 * Tests for the WP_Plugin_Dependencies::initialize() method.
 *
 * @package WordPress
 */

require_once __DIR__ . '/base.php';

/**
 * @group admin
 * @group plugins
 *
 * @covers WP_Plugin_Dependencies::initialize
 */
class Tests_Admin_WPPluginDependencies_Initialize extends WP_PluginDependencies_UnitTestCase {

	/**
	 * Tests that initialization runs only once.
	 *
	 * @ticket 60457
	 *
	 * @dataProvider data_static_properties_set_during_initialization
	 *
	 * @param string $property_name The name of the property to check.
	 */
	public function test_should_only_initialize_once( $property_name ) {
		$this->assertFalse(
			$this->get_property_value( 'initialized' ),
			'Plugin Dependencies has already been initialized.'
		);

		self::$instance->initialize();

		$this->assertTrue(
			$this->get_property_value( 'initialized' ),
			'"initialized" was not set to true during initialization.'
		);

		$default_value = self::$static_properties[ $property_name ];

		$this->assertNotSame(
			$default_value,
			$this->get_property_value( $property_name ),
			"\"{$property_name}\" was not set during initialization."
		);

		// Reset it to its default.
		$this->set_property_value( $property_name, self::$static_properties[ $property_name ] );

		self::$instance->initialize();

		$this->assertSame(
			$default_value,
			$this->get_property_value( $property_name ),
			"\"{$property_name}\" was set during the second initialization attempt."
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_static_properties_set_during_initialization() {
		/*
		 * This does not include 'dependency_api_data' as it is only set
		 * on certain pages. This is tested later.
		 */
		return self::text_array_to_dataprovider(
			array(
				'plugins',
				'dependencies',
				'dependency_slugs',
				'dependent_slugs',
			)
		);
	}

	/**
	 * Tests that `$dependency_api_data` is set on certain screens.
	 *
	 * @ticket 22316
	 *
	 * @covers WP_Plugin_Dependencies::get_dependency_api_data
	 * @covers WP_Plugin_Dependencies::get_plugins
	 *
	 * @dataProvider data_screens
	 *
	 * @global string $pagenow The filename of the current screen.
	 *
	 * @param string $screen The screen file.
	 */
	public function test_should_set_dependency_api_data_on_certain_screens( $screen ) {
		global $pagenow;

		// Backup $pagenow.
		$old_pagenow = $pagenow;

		// Ensure is_admin() and screen checks pass.
		$pagenow = $screen;
		set_current_screen( $screen );

		self::$instance::initialize();

		// Restore $pagenow.
		$pagenow = $old_pagenow;

		$dependency_api_data = $this->get_property_value( 'dependency_api_data' );

		$this->assertIsArray( $dependency_api_data, '$dependency_api_data is not an array.' );
		$this->assertEmpty( $dependency_api_data, '$dependency_api_data is not empty.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_screens() {
		return array(
			'plugins.php'        => array(
				'screen' => 'plugins.php',
			),
			'plugin-install.php' => array(
				'screen' => 'plugin-install.php',
			),
		);
	}

	/**
	 * Tests that `$dependency_api_data` is not set by default.
	 *
	 * @ticket 22316
	 *
	 * @covers WP_Plugin_Dependencies::get_dependency_api_data
	 */
	public function test_should_not_set_dependency_api_data() {
		self::$instance::initialize();

		$dependency_api_data = $this->get_property_value( 'dependency_api_data' );

		$this->assertNull( $dependency_api_data, '$dependency_api_data was set.' );
	}

	/**
	 * Tests that dependency slugs are loaded and sanitized.
	 *
	 * @ticket 22316
	 *
	 * @covers WP_Plugin_Dependencies::read_dependencies_from_plugin_headers
	 * @covers WP_Plugin_Dependencies::sanitize_dependency_slugs
	 *
	 * @dataProvider data_should_sanitize_slugs
	 *
	 * @param string $requires_plugins The unsanitized dependency slug(s).
	 * @param array  $expected         Optional. The sanitized dependency slug(s). Default empty array.
	 */
	public function test_initialize_should_load_and_sanitize_dependency_slugs_from_plugin_headers( $requires_plugins, $expected = array() ) {
		$this->set_property_value( 'plugins', array( 'dependent/dependent.php' => array( 'RequiresPlugins' => $requires_plugins ) ) );
		self::$instance->initialize();
		$this->assertSame( $expected, $this->get_property_value( 'dependency_slugs' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_sanitize_slugs() {
		return array(
			// Valid slugs.
			'one dependency'                         => array(
				'requires_plugins' => 'hello-dolly',
				'expected'         => array( 'hello-dolly' ),
			),
			'two dependencies in alphabetical order' => array(
				'requires_plugins' => 'hello-dolly, woocommerce',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'two dependencies in reverse alphabetical order' => array(
				'requires_plugins' => 'woocommerce, hello-dolly',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'two dependencies with a space'          => array(
				'requires_plugins' => 'hello-dolly , woocommerce',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'a repeated dependency'                  => array(
				'requires_plugins' => 'hello-dolly, woocommerce, hello-dolly',
				'expected'         => array(
					'hello-dolly',
					'woocommerce',
				),
			),
			'a dependency with multiple dashes'      => array(
				'requires_plugins' => 'this-is-a-valid-slug',
				'expected'         => array( 'this-is-a-valid-slug' ),
			),
			'a dependency starting with numbers'     => array(
				'requires_plugins' => '123slug',
				'expected'         => array( '123slug' ),
			),
			'a dependency with a trailing comma'     => array(
				'requires_plugins' => 'hello-dolly,',
				'expected'         => array( 'hello-dolly' ),
			),
			'a dependency with a leading comma'      => array(
				'requires_plugins' => ',hello-dolly',
				'expected'         => array( 'hello-dolly' ),
			),
			'a dependency with leading and trailing commas' => array(
				'requires_plugins' => ',hello-dolly,',
				'expected'         => array( 'hello-dolly' ),
			),
			'a dependency with a trailing comma and a space' => array(
				'requires_plugins' => 'hello-dolly, ',
				'expected'         => array( 'hello-dolly' ),
			),

			// Invalid or empty slugs.
			'no dependencies'                        => array(
				'requires_plugins' => '',
			),
			'a dependency with an underscore'        => array(
				'requires_plugins' => 'hello_dolly',
			),
			'a dependency with a space'              => array(
				'requires_plugins' => 'hello dolly',
			),
			'a dependency in quotes'                 => array(
				'requires_plugins' => '"hello-dolly"',
			),
			'two dependencies in quotes'             => array(
				'requires_plugins' => '"hello-dolly, woocommerce"',
			),
			'a dependency with trailing dash'        => array(
				'requires_plugins' => 'ending-dash-',
			),
			'a dependency with leading dash'         => array(
				'requires_plugins' => '-slug',
			),
			'a dependency with double dashes'        => array(
				'requires_plugins' => 'abc--123',
			),
			'cyrillic dependencies'                  => array(
				'requires_plugins' => 'я-делюсь',
			),
			'arabic dependencies'                    => array(
				'requires_plugins' => 'لينوكس-ويكى',
			),
			'chinese dependencies'                   => array(
				'requires_plugins' => '唐诗宋词chinese-poem,社交登录,腾讯微博一键登录,豆瓣秀-for-wordpress',
			),
			'symbol dependencies'                    => array(
				'requires_plugins' => '★-wpsymbols-★',
			),
		);
	}

	/**
	 * Tests that dependent files are loaded and slugified.
	 *
	 * @ticket 22316
	 *
	 * @covers WP_Plugin_Dependencies::read_dependencies_from_plugin_headers
	 * @covers WP_Plugin_Dependencies::convert_to_slug
	 */
	public function test_should_slugify_dependent_files() {
		$plugins = get_plugins();

		$expected_slugs = array();
		foreach ( $plugins as $plugin_file => &$headers ) {
			// Create the expected slugs.
			if ( 'hello.php' === $plugin_file ) {
				$slug = 'hello-dolly';
			} else {
				$slug = str_replace( '.php', '', explode( '/', $plugin_file )[0] );
			}

			$expected_slugs[ $plugin_file ] = $slug;

			// While here, ensure the plugins are all dependents.
			$headers['RequiresPlugins'] = 'dependency';
		}
		unset( $headers );

		// Set the plugins property with the plugin data modified to make them dependents.
		$this->set_property_value( 'plugins', $plugins );

		self::$instance->initialize();
		$this->assertSame( $expected_slugs, $this->get_property_value( 'dependent_slugs' ) );
	}
}
