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
	 * Tests that the `$dependencies` and `$dependency_api_data` properties are set to
	 * empty arrays on instantiation.
	 *
	 * @covers WP_Plugin_Dependencies::__construct
	 */
	public function test_construct_should_set_dependencies_and_dependency_api_data_to_empty_arrays() {
		$wppd                = new WP_Plugin_Dependencies();
		$dependencies        = $this->make_prop_accessible( $wppd, 'dependencies' );
		$dependency_api_data = $this->make_prop_accessible( $wppd, 'dependency_api_data' );

		$actual_dependencies        = $dependencies->getValue( $wppd );
		$actual_dependency_api_data = $dependency_api_data->getValue( $wppd );

		$this->assertIsArray( $actual_dependencies, '$dependencies is not an array.' );
		$this->assertEmpty( $actual_dependencies, '$dependencies is not empty.' );
		$this->assertIsArray( $actual_dependency_api_data, '$dependency_api_data is not an array.' );
		$this->assertEmpty( $actual_dependency_api_data, '$dependency_api_data is not empty.' );
	}

	/**
	 * Tests that `::get_plugins()` returns an array of plugin data.
	 *
	 * @covers WP_Plugin_Dependencies::get_plugins
	 */
	public function test_get_plugins_should_return_an_array_of_plugin_data() {
		$wppd        = new WP_Plugin_Dependencies();
		$get_plugins = $this->make_method_accessible( $wppd, 'get_plugins' );
		$get_plugins->invoke( $wppd );

		$plugins = $this->make_prop_accessible( $wppd, 'plugins' );
		$actual = $plugins->getValue( $wppd );

		$this->assertIsArray( $actual, 'Did not return an array.' );
		$this->assertNotEmpty( $actual, 'The plugin data array is empty.' );
	}

	/**
	 * Tests that plugin headers are correctly parsed.
	 *
	 * @dataProvider data_sanitize_dependency_slugs
	 *
	 * @covers WP_Plugin_Dependencies::sanitize_dependency_slugs
	 *
	 * @param array $requires_plugins Raw value of the 'Requires Plugins' header.
	 * @param array $expected         The expected parsed headers.
	 */
	public function test_sanitize_dependency_slugs( $requires_plugins, $expected ) {
		$wppd = new WP_Plugin_Dependencies();

		$sanitize_dependency_slugs = $this->make_method_accessible( $wppd, 'sanitize_dependency_slugs' );
		$actual                    = $sanitize_dependency_slugs->invoke( $wppd, $requires_plugins );

		$this->assertEqualSets( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_sanitize_dependency_slugs() {
		return array(
			'no dependencies'                        => array(
				'',
				'expected'     => array(),
			),
			'one dependency'                         => array(
				'requires_plugins' => 'hello-dolly',
				'expected'     => array( 'hello-dolly' ),
			),
			'two dependencies in alphabetical order' => array(
				'requires_plugins' => 'hello-dolly, woocommerce',
				'expected'     => array( 'hello-dolly', 'woocommerce' ),
			),
			'two dependencies in reverse alphabetical order' => array(
				'requires_plugins' => 'woocommerce, hello-dolly',
				'expected'     => array( 'woocommerce', 'hello-dolly' ),
			),
			'two dependencies with a space'          => array(
				'requires_plugins' => 'hello-dolly , woocommerce',
				'expected'     => array( 'hello-dolly', 'woocommerce' ),
			),
			'a repeated dependency'                  => array(
				'requires_plugins' => 'hello-dolly, woocommerce, hello-dolly',
				'expected'     => array( 'hello-dolly', 'woocommerce' ),
			),
			'a dependency with an underscore'        => array(
				'requires_plugins' => 'hello_dolly',
				'expected'     => array(),
			),
			'a dependency with a space'              => array(
				'requires_plugins' => 'hello dolly',
				'expected'     => array(),
			),
			'a dependency in quotes'                 => array(
				'requires_plugins' => '"hello-dolly"',
				'expected'     => array(),
			),
			'two dependencies in quotes'             => array(
				'requires_plugins' => '"hello-dolly, woocommerce"',
				'expected'     => array(),
			),
			'cyrillic dependencies'                  => array(
				'requires_plugins' => 'я-делюсь',
				'expected'     => array(),
			),
			'arabic dependencies'                    => array(
				'requires_plugins' => 'لينوكس-ويكى',
				'expected'     => array(),
			),
			'chinese dependencies'                   => array(
				'requires_plugins' => '唐诗宋词chinese-poem,社交登录,腾讯微博一键登录,豆瓣秀-for-wordpress',
				'expected'     => array(),
			),
			'symbol dependencies'                    => array(
				'requires_plugins' => '★-wpsymbols-★',
				'expected'     => array(),
			),
		);
	}

	/**
	 * Tests that slugs are correctly sanitized from the 'RequiresPlugins' header.
	 *
	 * @dataProvider data_slug_sanitization
	 *
	 * @covers WP_Plugin_Dependencies::sanitize_dependency_slugs
	 *
	 * @param string $requires_plugins The unsanitized dependency slug(s).
	 * @param array  $expected         The sanitized dependency slug(s).
	 */
	public function test_slugs_are_correctly_sanitized_from_the_requiresplugins_header( $requires_plugins, $expected ) {
		$wppd     = new WP_Plugin_Dependencies();
		$sanitize = $this->make_method_accessible( $wppd, 'sanitize_dependency_slugs' );
		$actual   = $sanitize->invoke( $wppd, $requires_plugins );
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
		$wppd               = new WP_Plugin_Dependencies();
		$dependency_slugs   = $this->make_prop_accessible( $wppd, 'dependency_slugs' );
		$dependency_plugins = $this->make_prop_accessible( $wppd, 'plugins' );
		$get_filepaths      = $this->make_method_accessible( $wppd, 'get_dependency_filepaths' );

		$dependency_slugs->setValue( $wppd, $slugs );
		$dependency_plugins->setValue( $wppd, array_flip( $plugins ) );

		$actual = $get_filepaths->invoke( $wppd );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_get_dependency_filepaths() {
		return array(
			'no slugs'                                     => array(
				'dependency_slugs' => array(),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected'         => array(),
			),
			'no plugins'                                   => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array(),
				'expected'         => array(),
			),
			'a plugin that starts with slug/'              => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( 'plugin1-pro/plugin1.php' ),
				'expected'         => array( 'plugin1' => false ),
			),
			'a plugin that ends with slug/'                => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( 'addon-for-plugin1/plugin1.php' ),
				'expected'         => array( 'plugin1' => false ),
			),
			'a plugin that does not exist'                 => array(
				'dependency_slugs' => array( 'plugin2' ),
				'plugins'          => array( 'plugin1/plugin1.php' ),
				'expected'         => array( 'plugin2' => false ),
			),
			'a plugin that exists'                         => array(
				'dependency_slugs' => array( 'plugin1' ),
				'plugins'          => array( 'plugin1/plugin1.php' ),
				'expected'         => array( 'plugin1' => 'plugin1/plugin1.php' ),
			),
			'two plugins that exist'                       => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected'         => array(
					'plugin1' => 'plugin1/plugin1.php',
					'plugin2' => 'plugin2/plugin2.php',
				),
			),
			'two plugins that exist (reversed slug order)' => array(
				'dependency_slugs' => array( 'plugin2', 'plugin1' ),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin2/plugin2.php' ),
				'expected'         => array(
					'plugin2' => 'plugin2/plugin2.php',
					'plugin1' => 'plugin1/plugin1.php',
				),
			),
			'two plugins, first exists, second does not exist' => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin1/plugin1.php', 'plugin3/plugin3.php' ),
				'expected'         => array(
					'plugin1' => 'plugin1/plugin1.php',
					'plugin2' => false,
				),
			),
			'two plugins, first does not exist, second does exist' => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin2/plugin2.php', 'plugin3/plugin3.php' ),
				'expected'         => array(
					'plugin1' => false,
					'plugin2' => 'plugin2/plugin2.php',
				),
			),
			'two plugins that do not exist'                => array(
				'dependency_slugs' => array( 'plugin1', 'plugin2' ),
				'plugins'          => array( 'plugin3/plugin3.php', 'plugin4/plugin4.php' ),
				'expected'         => array(
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
		$wppd                      = new WP_Plugin_Dependencies();
		$get_filepaths             = $this->make_method_accessible( $wppd, 'get_dependency_filepaths' );
		$dependency_slugs          = $this->make_prop_accessible( $wppd, 'dependency_slugs' );
		$dependency_plugins        = $this->make_prop_accessible( $wppd, 'plugins' );
		$dependency_dirnames       = $this->make_prop_accessible( $wppd, 'plugin_dirnames' );
		$dependency_dirnames_cache = $this->make_prop_accessible( $wppd, 'plugin_dirnames_cache' );

		$dependency_dirnames_cache->setValue(
			$wppd,
			array(
				'plugin1/plugin1.php',
				'plugin2/plugin2.php',
			)
		);

		// An additional plugin has been added during runtime.
		$dependency_slugs->setValue( $wppd, array( 'plugin1', 'plugin2', 'plugin3' ) );
		$dependency_plugins->setValue(
			$wppd,
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
		$dependency_dirnames->setValue( $wppd, $expected );

		$this->assertSame( $expected, $get_filepaths->invoke( $wppd ) );
	}
}
