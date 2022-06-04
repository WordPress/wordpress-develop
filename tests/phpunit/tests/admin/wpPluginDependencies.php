<?php
/**
 * Test WP_Plugin_Dependencies class.
 *
 * @package WP_Plugin_Dependencies
 *
 * @group admin
 * @group plugins
 */

class Tests_Admin_WpPluginDependencies extends WP_UnitTestCase {
	protected static $plugin_dir;

	public static function wpSetUpBeforeClass() {
		self::$plugin_dir = WP_PLUGIN_DIR . '/wp_plugin_dependencies_plugin';
		@mkdir( self::$plugin_dir );
	}

	public static function wpTearDownAfterClass() {
		array_map( 'unlink', array_filter( (array) glob( self::$plugin_dir . '/*' ) ) );
		rmdir( self::$plugin_dir );
	}

	/**
	 * Helper method.
	 *
	 * This creates a single-file plugin.
	 *
	 * @access private
	 *
	 * @param string $data     Optional. Data for the plugin file. Default is a dummy plugin header.
	 * @param string $filename Optional. Filename for the plugin file. Default is a random string.
	 * @param string $dir_path Optional. Path for directory where the plugin should live.
	 * @return array Two-membered array of filename and full plugin path.
	 */
	private function create_plugin( $filename, $data = "<?php\n/*\nPlugin Name: Test\n*/", $dir_path = false ) {
		if ( false === $filename ) {
			$filename = __FUNCTION__ . '.php';
		}

		if ( false === $dir_path ) {
			$dir_path = WP_PLUGIN_DIR;
		}

		$filename  = wp_unique_filename( $dir_path, $filename );
		$full_name = $dir_path . '/' . $filename;

		$file = fopen( $full_name, 'w' );
		fwrite( $file, $data );
		fclose( $file );

		return array( $filename, $full_name );
	}

	/**
	 * Helper method.
	 *
	 * This makes a class property accessible.
	 *
	 * @param object|string $obj_or_class The object or class.
	 * @param string        $prop         The property.
	 * @return ReflectionProperty The accessible property.
	 */
	private function make_prop_accessible( $obj_or_class, $prop ) {
		$property = new ReflectionProperty( $obj_or_class, $prop );
		$property->setAccessible( true );
		return $property;
	}

	/**
	 * Helper method.
	 *
	 * Makes a class function accessible.
	 *
	 * @param object|string $obj_or_class The object or class.
	 * @param string        $function     The class method.
	 * @return ReflectionMethod The accessible method.
	 */
	private function make_method_accessible( $obj_or_class, $function ) {
		$method = new ReflectionMethod( $obj_or_class, $function );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * @covers WP_Plugin_Dependencies::__construct()
	 */
	public function test__construct() {
		$dependencies     = new WP_Plugin_Dependencies();
		$requires_plugins = $this->make_prop_accessible( $dependencies, 'requires_plugins' );
		$plugin_data      = $this->make_prop_accessible( $dependencies, 'plugin_data' );

		$actual = $requires_plugins->getValue( $dependencies );

		$this->assertIsArray( $actual, '$requires_plugins is not an array' );
		$this->assertEmpty( $actual, '$requires_plugins is not empty' );

		$actual = $plugin_data->getValue( $dependencies );

		$this->assertIsArray( $actual, '$plugin_data is not an array' );
		$this->assertEmpty( $actual, '$plugin_data is not empty' );
	}

	/**
	 * @covers WP_Plugin_Dependencies::get_plugins
	 */
	public function test_get_plugins() {
		$dependencies = new WP_Plugin_Dependencies();
		$get_plugins  = $this->make_method_accessible( $dependencies, 'get_plugins');
		$actual       = $get_plugins->invoke( $dependencies );

		$this->assertIsArray( $actual, 'Did not return an array' );
		$this->assertNotEmpty( $actual, 'The plugins array is empty' );
	}

	/**
	 * @dataProvider data_parse_plugin_headers
	 *
	 * @covers WP_Plugin_Dependencies::parse_plugin_headers
	 *
	 * @param array    $headers .
	 * @param stdClass $expected     The expected parsed headers.
	 */
	public function test_parse_plugin_headers( $headers, $expected ) {
		$plugin_names = array();

		foreach ( $headers as $plugin_name => $plugin ) {
			$plugin_data = array_map(
				static function( $value, $header ) {
					return $header . ': ' . $value;
				},
				$plugin,
				array_keys( $plugin )
			);

			$plugin_data = "<?php\n/*\n" . implode( "\n", $plugin_data ) . "\n*/\n";

			$plugin_file = $this->create_plugin(
				$plugin_name . '.php',
				$plugin_data,
				self::$plugin_dir
			);

			$plugin_names[] = $plugin_file[1];
		}

		get_plugins();

		$dependencies = new WP_Plugin_Dependencies();
		$plugins      = $this->make_prop_accessible( $dependencies, 'plugins' );
		$plugins->setValue( $dependencies, $headers );

		$parse_plugin_headers = $this->make_method_accessible( $dependencies, 'parse_plugin_headers');
		$actual               = $parse_plugin_headers->invoke( $dependencies );

		// Remove any non testing data, may be single file plugins in test environment.
		$test_plugin = basename( self::$plugin_dir ) . '/' . $plugin_file[0];
		$actual      = array_filter( $actual, function( $key ) use ( $test_plugin ) {
			return $test_plugin === $key;
		}, ARRAY_FILTER_USE_KEY );

		foreach ( $plugin_names as $plugin_name ) {
			if ( $expected ) {
				$expected = array( str_replace( WP_PLUGIN_DIR . '/', '', $plugin_name ) => $expected );
			}
			unlink( $plugin_name );
		}

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data Provider.
	 *
	 * @return array
	 */
	public function data_parse_plugin_headers() {
		return array(
			'no dependencies'                        => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name' => 'Test Plugin',
					),
				),
				'expected'     => array(),
			),
			'one dependency'                         => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'hello-dolly',
					),
				),
				'expected'     => array(
					'RequiresPlugins' => 'hello-dolly',
				),
			),
			'two dependencies in alphabetical order' => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'hello-dolly, woocommerce',
					),
				),
				'expected'     => array(
					'RequiresPlugins' => 'hello-dolly, woocommerce',
				),
			),
			'two dependencies in reverse alphabetical order' => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'woocommerce, hello-dolly',
					),
				),
				'expected'     => array(
					'RequiresPlugins' => 'woocommerce, hello-dolly',
				),
			),
			'two dependencies with a space'          => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'hello-dolly , woocommerce',
					),
				),
				'expected'     => array(
					'RequiresPlugins' => 'hello-dolly , woocommerce',
				),
			),
			'a repeated dependency'                  => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'hello-dolly, woocommerce, hello-dolly',
					),
				),
				'expected'     => array(
					'RequiresPlugins' => 'hello-dolly, woocommerce, hello-dolly',
				),
			),
			'a dependency with an underscore'        => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'hello_dolly',
					),
				),
				'expected'     => array( 'RequiresPlugins' => 'hello_dolly' ),
			),
			'a dependency with a space'              => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'hello dolly',
					),
				),
				'expected'     => array( 'RequiresPlugins' => 'hello dolly' ),
			),
			'a dependency in quotes'                 => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => '"hello-dolly"',
					),
				),
				'expected'     => array( 'RequiresPlugins' => '"hello-dolly"' ),
			),
			'two dependencies in quotes'             => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => '"hello-dolly, woocommerce"',
					),
				),
				'expected'     => array( 'RequiresPlugins' => '"hello-dolly, woocommerce"' ),
			),
		);
	}

	/**
	 * @dataProvider data_slug_sanitization
	 *
	 * @covers WP_Plugin_Dependencies::sanitize_required_headers
	 *
	 * @param string $requires_plugins The unsanitized dependency slug(s).
	 * @param array  $expected         The sanitized dependency slug(s).
	 */
	public function test_slug_sanitization( $requires_plugins, $expected ) {
		$dependencies = new WP_Plugin_Dependencies();
		$sanitize     = $this->make_method_accessible( $dependencies, 'sanitize_required_headers');
		$headers      = array( 'test-plugin' => array( 'RequiresPlugins' => $requires_plugins ) );
		$actual       = $sanitize->invoke( $dependencies, $headers );
		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_slug_sanitization() {
		return array(
			'one dependency'                         => array(
				'requires_plugins' => 'hello-dolly',
				'expected'         => array( 'hello-dolly' ),
			),
			'two dependencies in alphabetical order' => array(
				'requires_plugins' => 'hello-dolly, woocommerce',
				'expected'         => array( 'hello-dolly', 'woocommerce' ),
			),
			'two dependencies in reverse alphabetical order' => array(
				'requires_plugins' => 'woocommerce, hello-dolly',
				'expected'         => array( 'hello-dolly', 'woocommerce' ),
			),
			'two dependencies with a space'          => array(
				'requires_plugins' => 'hello-dolly , woocommerce',
				'expected'         => array( 'hello-dolly', 'woocommerce' ),
			),
			'a repeated dependency'                  => array(
				'requires_plugins' => 'hello-dolly, woocommerce, hello-dolly',
				'expected'         => array( 'hello-dolly', 'woocommerce' ),
			),
			'a dependency with an underscore'        => array(
				'requires_plugins' => 'hello_dolly',
				'expected'         => array(),
			),
			'a dependency with a space'              => array(
				'requires_plugins' => 'hello dolly',
				'expected'         => array(),
			),
			'a dependency in quotes'                 => array(
				'requires_plugins' => '"hello-dolly"',
				'expected'         => array(),
			),
			'two dependencies in quotes'             => array(
				'requires_plugins' => '"hello-dolly, woocommerce"',
				'expected'         => array(),
			),
		);
	}
}
