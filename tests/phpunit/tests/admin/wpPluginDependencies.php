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
	/**
	 * Stored the plugins directory.
	 *
	 * @var string
	 */
	protected static $plugins_dir;

	/**
	 * Sets up the plugins directory before any tests run.
	 */
	public static function set_up_before_class() {
		self::$plugins_dir = WP_PLUGIN_DIR . '/wp_plugin_dependencies_plugin';
		@mkdir( self::$plugins_dir );
	}

	/**
	 * Removes the plugins directory after all tests run.
	 */
	public static function tear_down_after_class() {
		array_map( 'unlink', array_filter( (array) glob( self::$plugins_dir . '/*' ) ) );
		rmdir( self::$plugins_dir );
	}

	/**
	 * Creates a single-file plugin.
	 *
	 * @param string $data     Optional. Data for the plugin file. Default is a dummy plugin header.
	 * @param string $filename Optional. Filename for the plugin file. Default is a random string.
	 * @param string $dir_path Optional. Path for directory where the plugin should live.
	 * @return array Two-membered array of filename and full plugin path.
	 */
	private function create_plugin( $filename, $data = "<?php\n/*\nPlugin Name: Test\n*/", $dir_path = false ) {
		if ( false === $filename ) {
			$filename = 'create_plugin.php';
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
	 * Makes a class property accessible.
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
	 * Makes a class method accessible.
	 *
	 * @param object|string $obj_or_class The object or class.
	 * @param string        $method     The class method.
	 * @return ReflectionMethod The accessible method.
	 */
	private function make_method_accessible( $obj_or_class, $method ) {
		$method = new ReflectionMethod( $obj_or_class, $method );
		$method->setAccessible( true );
		return $method;
	}

	/**
	 * Tests that the `$requires_plugins` and `$plugin_data` properties are set to
	 * empty arrays on instantiation.
	 *
	 * @covers WP_Plugin_Dependencies::__construct
	 */
	public function test_construct_should_set_requires_plugins_and_plugin_data_to_empty_arrays() {
		$dependencies     = new WP_Plugin_Dependencies();
		$requires_plugins = $this->make_prop_accessible( $dependencies, 'requires_plugins' );
		$plugin_data      = $this->make_prop_accessible( $dependencies, 'plugin_data' );

		$actual_requires_plugins = $requires_plugins->getValue( $dependencies );
		$actual_plugin_data      = $plugin_data->getValue( $dependencies );

		$this->assertIsArray( $actual_requires_plugins, '$requires_plugins is not an array.' );
		$this->assertEmpty( $actual_requires_plugins, '$requires_plugins is not empty.' );
		$this->assertIsArray( $actual_plugin_data, '$plugin_data is not an array.' );
		$this->assertEmpty( $actual_plugin_data, '$plugin_data is not empty.' );
	}

	/**
	 * Tests that `::get_plugins()` returns an array of plugin data.
	 *
	 * @covers WP_Plugin_Dependencies::get_plugins
	 */
	public function test_get_plugins_should_return_an_array_of_plugin_data() {
		$dependencies = new WP_Plugin_Dependencies();
		$get_plugins  = $this->make_method_accessible( $dependencies, 'get_plugins' );
		$actual       = $get_plugins->invoke( $dependencies );

		$this->assertIsArray( $actual, 'Did not return an array.' );
		$this->assertNotEmpty( $actual, 'The plugin data array is empty.' );
	}

	/**
	 * Tests that plugin headers are correctly parsed.
	 *
	 * @dataProvider data_parse_plugin_headers
	 *
	 * @covers WP_Plugin_Dependencies::parse_plugin_headers
	 *
	 * @param array    $plugins_data Raw plugins data.
	 * @param stdClass $expected     The expected parsed headers.
	 */
	public function test_parse_plugin_headers( $plugins_data, $expected ) {
		$plugin_names = array();

		foreach ( $plugins_data as $name => $data ) {
			$plugin_data = array_map(
				static function( $value, $header ) {
					return $header . ': ' . $value;
				},
				$data,
				array_keys( $data )
			);

			$plugin_data = "<?php\n/*\n" . implode( "\n", $plugin_data ) . "\n*/\n";

			$plugin_file = $this->create_plugin(
				$name . '.php',
				$plugin_data,
				self::$plugins_dir
			);

			$plugin_names[] = $plugin_file[1];
		}

		get_plugins();

		$dependencies = new WP_Plugin_Dependencies();
		$plugins      = $this->make_prop_accessible( $dependencies, 'plugins' );
		$plugins->setValue( $dependencies, $plugins_data );

		$parse_plugin_headers = $this->make_method_accessible( $dependencies, 'parse_plugin_headers' );
		$actual               = $parse_plugin_headers->invoke( $dependencies );

		// Remove any non testing data, may be single file plugins in test environment.
		$test_plugin = basename( self::$plugins_dir ) . '/' . $plugin_file[0];
		$actual      = array_filter(
			$actual,
			function( $key ) use ( $test_plugin ) {
				return $test_plugin === $key;
			},
			ARRAY_FILTER_USE_KEY
		);

		foreach ( $plugin_names as $plugin_name ) {
			if ( $expected ) {
				$expected = array( str_replace( WP_PLUGIN_DIR . '/', '', $plugin_name ) => $expected );
			}
			unlink( $plugin_name );
		}

		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
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
			'cyrillic dependencies'                  => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'я-делюсь',
					),
				),
				'expected'     => array( 'RequiresPlugins' => 'я-делюсь' ),
			),
			'arabic dependencies'                    => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => 'لينوكس-ويكى',
					),
				),
				'expected'     => array( 'RequiresPlugins' => 'لينوكس-ويكى' ),
			),
			'chinese dependencies'                   => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => '唐诗宋词chinese-poem,社交登录,腾讯微博一键登录,豆瓣秀-for-wordpress',
					),
				),
				'expected'     => array( 'RequiresPlugins' => '唐诗宋词chinese-poem,社交登录,腾讯微博一键登录,豆瓣秀-for-wordpress' ),
			),
			'symbol dependencies'                    => array(
				'plugins_data' => array(
					'test-plugin' => array(
						'Plugin Name'      => 'Test Plugin',
						'Requires Plugins' => '★-wpsymbols-★',
					),
				),
				'expected'     => array( 'RequiresPlugins' => '★-wpsymbols-★' ),
			),
		);
	}

	/**
	 * Tests that slugs are correctly sanitized from the 'RequiresPlugins' header.
	 *
	 * @dataProvider data_slug_sanitization
	 *
	 * @covers WP_Plugin_Dependencies::sanitize_required_headers
	 *
	 * @param string $requires_plugins The unsanitized dependency slug(s).
	 * @param array  $expected         The sanitized dependency slug(s).
	 */
	public function test_slugs_are_correctly_sanitized_from_the_requiresplugins_header( $requires_plugins, $expected ) {
		$dependencies = new WP_Plugin_Dependencies();
		$sanitize     = $this->make_method_accessible( $dependencies, 'sanitize_required_headers' );
		$headers      = array( 'test-plugin' => array( 'RequiresPlugins' => $requires_plugins ) );
		$actual       = $sanitize->invoke( $dependencies, $headers );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
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
			'a dependency with multiple dashes'      => array(
				'requires_plugins' => 'this-is-a-valid-slug',
				'expected'         => array( 'this-is-a-valid-slug' ),
			),
			'a dependency with trailing dash'        => array(
				'requires_plugins' => 'ending-dash-',
				'expected'         => array(),
			),
			'a dependency with leading dash'         => array(
				'requires_plugins' => '-slug',
				'expected'         => array(),
			),
			'a dependency with double dashes'        => array(
				'requires_plugins' => 'abc--123',
				'expected'         => array(),
			),
			'a dependency starting with numbers'     => array(
				'requires_plugins' => '123slug',
				'expected'         => array( '123slug' ),
			),
			'cyrillic dependencies'                  => array(
				'requires_plugins' => 'я-делюсь',
				'expected'         => array(),
			),
			'arabic dependencies'                    => array(
				'requires_plugins' => 'لينوكس-ويكى',
				'expected'         => array(),
			),
			'chinese dependencies'                   => array(
				'requires_plugins' => '唐诗宋词chinese-poem,社交登录,腾讯微博一键登录,豆瓣秀-for-wordpress',
				'expected'         => array(),
			),
			'symbol dependencies'                    => array(
				'requires_plugins' => '★-wpsymbols-★',
				'expected'         => array(),
			),
		);
	}

	/**
	 * Tests that dependency filepaths are retrieved correctly.
	 *
	 * @covers WP_Plugin_Dependencies::get_dependency_filepaths
	 *
	 * @dataProvider data_get_dependency_filepaths
	 *
	 * @param string[] $slugs    An array of slugs.
	 * @param string[] $plugins  An array of plugin paths.
	 * @param array    $expected An array of expected filepath results.
	 */
	public function test_get_dependency_filepaths( $slugs, $plugins, $expected ) {
		$dependencies       = new WP_Plugin_Dependencies();
		$get_filepaths      = $this->make_method_accessible( $dependencies, 'get_dependency_filepaths' );
		$dependency_slugs   = $this->make_prop_accessible( $dependencies, 'slugs' );
		$dependency_plugins = $this->make_prop_accessible( $dependencies, 'plugins' );

		$dependency_slugs->setValue( $dependencies, $slugs );
		$dependency_plugins->setValue( $dependencies, array_flip( $plugins ) );

		$this->assertSame( $expected, $get_filepaths->invoke( $dependencies ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_dependency_filepaths() {
		return array(
			'no slugs'                                     => array(
				'slugs'    => array(),
				'plugins'  => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected' => array(),
			),
			'no plugins'                                   => array(
				'slugs'    => array( 'plugin1', 'plugin2' ),
				'plugins'  => array(),
				'expected' => array(),
			),
			'a plugin that starts with slug/'              => array(
				'slugs'    => array( 'plugin1' ),
				'plugins'  => array( 'plugin1-pro/plugin1.php' ),
				'expected' => array( 'plugin1' => false ),
			),
			'a plugin that ends with slug/'                => array(
				'slugs'    => array( 'plugin1' ),
				'plugins'  => array( 'addon-for-plugin1/plugin1.php' ),
				'expected' => array( 'plugin1' => false ),
			),
			'a plugin that does not exist'                 => array(
				'slugs'    => array( 'plugin2' ),
				'plugins'  => array( 'plugin1/plugin1.php' ),
				'expected' => array( 'plugin2' => false ),
			),
			'a plugin that exists'                         => array(
				'slugs'    => array( 'plugin1' ),
				'plugins'  => array( 'plugin1/plugin1.php' ),
				'expected' => array( 'plugin1' => 'plugin1/plugin1.php' ),
			),
			'two plugins that exist'                       => array(
				'slugs'    => array( 'plugin1', 'plugin2' ),
				'plugins'  => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected' => array(
					'plugin1' => 'plugin1/plugin1.php',
					'plugin2' => 'plugin2/plugin2.php',
				),
			),
			'two plugins that exist (reversed slug order)' => array(
				'slugs'    => array( 'plugin2', 'plugin1' ),
				'plugins'  => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected' => array(
					'plugin2' => 'plugin2/plugin2.php',
					'plugin1' => 'plugin1/plugin1.php',
				),
			),
			'two plugins, first exists, second does not exist' => array(
				'slugs'    => array( 'plugin1', 'plugin2' ),
				'plugins'  => array( 'plugin1/plugin1.php', 'plugin3/plugin3.php' ),
				'expected' => array(
					'plugin1' => 'plugin1/plugin1.php',
					'plugin2' => false,
				),
			),
			'two plugins, first does not exist, second does exist' => array(
				'slugs'    => array( 'plugin1', 'plugin2' ),
				'plugins'  => array( 'plugin2/plugin2.php', 'plugin3/plugin3.php' ),
				'expected' => array(
					'plugin1' => false,
					'plugin2' => 'plugin2/plugin2.php',
				),
			),
			'two plugins that do not exist'                => array(
				'slugs'    => array( 'plugin1', 'plugin2' ),
				'plugins'  => array( 'plugin3/plugin3.php', 'plugin4/plugin4.php' ),
				'expected' => array(
					'plugin1' => false,
					'plugin2' => false,
				),
			),
		);
	}

	/**
	 * Tests that the plugin directory name cache is updated when
	 * it does not match the list of current plugins.
	 *
	 * @covers WP_Plugin_Dependencies::get_dependency_filepaths
	 */
	public function test_get_dependency_filepaths_with_unmatched_dirnames_and_dirnames_cache() {
		$dependencies              = new WP_Plugin_Dependencies();
		$get_filepaths             = $this->make_method_accessible( $dependencies, 'get_dependency_filepaths' );
		$dependency_slugs          = $this->make_prop_accessible( $dependencies, 'slugs' );
		$dependency_plugins        = $this->make_prop_accessible( $dependencies, 'plugins' );
		$dependency_dirnames       = $this->make_prop_accessible( $dependencies, 'plugin_dirnames' );
		$dependency_dirnames_cache = $this->make_prop_accessible( $dependencies, 'plugin_dirnames_cache' );

		$dependency_dirnames_cache->setValue(
			$dependencies,
			array(
				'plugin1/plugin1.php',
				'plugin2/plugin2.php',
			)
		);

		// An additional plugin has been added during runtime.
		$dependency_slugs->setValue( $dependencies, array( 'plugin1', 'plugin2', 'plugin3' ) );
		$dependency_plugins->setValue(
			$dependencies,
			// This is flipped as paths are stored in the keys.
			array(
				'plugin1/plugin1.php' => '',
				'plugin2/plugin2.php' => '',
				'plugin3/plugin3.php' => '',
			)
		);

		$expected = array(
			'plugin1' => 'plugin1/plugin1.php',
			'plugin2' => 'plugin2/plugin2.php',
			'plugin3' => 'plugin3/plugin3.php',
		);

		// The cache no longer matches the stored directory names and should be refreshed.
		$dependency_dirnames->setValue( $dependencies, $expected );

		$this->assertSame( $expected, $get_filepaths->invoke( $dependencies ) );
	}

	/**
	 * Tests that dependency slugs are returned correctly.
	 *
	 * @covers WP_Plugin_Dependencies_2::split_slug
	 *
	 * @dataProvider data_split_slug_should_return_correct_slug
	 *
	 * @param string $slug     A slug string.
	 * @param array  $expected A string of expected slug results.
	 */
	public function test_split_slug_should_return_correct_slug( $slug, $expected ) {
		$this->markTestSkipped( 'must be revisited.' );

		$dependencies2 = new WP_Plugin_Dependencies_2();
		$split_slug    = $this->make_method_accessible( $dependencies2, 'split_slug' );

		// The slug is trimmed before being passed to the 'wp_plugin_dependencies_slug' filter.
		$actual = $split_slug->invoke( $dependencies2, trim( $slug ) );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_split_slug_should_return_correct_slug() {
		return array(
			'no slug, an endpoint, and one pipe at the start' => array(
				'slug'     => '|endpoint',
				'expected' => '|endpoint',
			),
			'no slug, an endpoint, and two pipes at the start' => array(
				'slug'     => '||endpoint',
				'expected' => '||endpoint',
			),
			'a slug, an endpoint, and one pipe in the middle' => array(
				'slug'     => 'slug|endpoint',
				'expected' => 'slug',
			),
			'a slug, an endpoint, and two pipes in the middle' => array(
				'slug'     => 'slug||endpoint',
				'expected' => 'slug||endpoint',
			),
			'a slug, no endpoint, and one pipe at the end' => array(
				'slug'     => 'slug|',
				'expected' => 'slug|',
			),
			'a slug, no endpoint, and two pipes at the end' => array(
				'slug'     => 'slug||',
				'expected' => 'slug||',
			),
			'a slug, no endpoint, and one pipe at the start and end' => array(
				'slug'     => '|slug|',
				'expected' => '|slug|',
			),
			'a slug, no endpoint, and two pipes at the start and end' => array(
				'slug'     => '||slug||',
				'expected' => '||slug||',
			),
			'a slug, an endpoint, and two pipes in the middle' => array(
				'slug'     => 'slug||endpoint',
				'expected' => 'slug||endpoint',
			),
			'a slug, an endpoint, and one pipe at the start, in the middle, and at the end' => array(
				'slug'     => '|slug|endpoint|',
				'expected' => '|slug|endpoint|',
			),
			'a slug, an endpoint, and one pipe at the start and end, and two pipes in the middle' => array(
				'slug'     => '|slug||endpoint|',
				'expected' => '|slug||endpoint|',
			),
			'a slug, an endpoint, and two pipes at the start and end, and one pipe in the middle' => array(
				'slug'     => '||slug|endpoint||',
				'expected' => '||slug|endpoint||',
			),
			'a slug, an endpoint, and two pipes at the start and end, and two pipes in the middle' => array(
				'slug'     => '||slug||endpoint||',
				'expected' => '||slug||endpoint||',
			),
			'a slug, an endpoint, and one pipe at the start and in the middle' => array(
				'slug'     => '|slug|endpoint',
				'expected' => '|slug|endpoint',
			),
			'a slug, an endpoint, and one pipe in the middle and at the end' => array(
				'slug'     => 'slug|endpoint|',
				'expected' => 'slug|endpoint|',
			),
			'a slug, an endpoint, and two spaces and a pipe at the start, and a pipe in the middle' => array(
				'slug'     => '  |slug|endpoint',
				'expected' => '|slug|endpoint',
			),
			'a slug, an endpoint, and two spaces before a pipe in the middle' => array(
				'slug'     => 'slug  |endpoint',
				'expected' => 'slug',
			),
			'a slug, an endpoint, and two spaces after a pipe in the middle' => array(
				'slug'     => 'slug|  endpoint',
				'expected' => 'slug',
			),
			'a slug, an endpoint, and a pipe in the middle, a pipe at the end, and two spaces at the end' => array(
				'slug'     => 'slug|endpoint|  ',
				'expected' => 'slug|endpoint|',
			),
			'a slug, an endpoint, and spaces pipe at front pipe in middle' => array(
				'slug'     => '     |slug|endpoint',
				'expected' => '|slug|endpoint',
			),
			'no slug, no endpoint, and one pipe'           => array(
				'slug'     => '|',
				'expected' => '|',
			),
			'no slug, no endpoint, and two pipes'          => array(
				'slug'     => '||',
				'expected' => '||',
			),
			'an empty slug'                                => array(
				'slug'     => '',
				'expected' => '',
			),
		);
	}
}
