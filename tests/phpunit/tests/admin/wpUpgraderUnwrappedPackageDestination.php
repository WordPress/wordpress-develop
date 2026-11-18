<?php

/**
 * Tests the folder name a package is installed into by WP_Upgrader::install_package().
 *
 * @group admin
 * @group upgrade
 *
 * @covers WP_Upgrader::unpack_package
 * @covers WP_Upgrader::install_package
 */
class Tests_Admin_WpUpgraderUnwrappedPackageDestination extends WP_UnitTestCase {

	/**
	 * The name of the test package.
	 *
	 * @var string
	 */
	const PACKAGE = 'wp-upgrader-destination-test';

	/**
	 * Paths to the package files created by a test.
	 *
	 * @var string[]
	 */
	private $packages = array();

	/**
	 * Loads the upgrader classes.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Sets up a direct filesystem.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'The ZipArchive class is required for this test.' );
		}

		add_filter( 'filesystem_method', array( $this, 'filter_filesystem_method' ) );
		WP_Filesystem();

		$this->remove_installed_package();
	}

	/**
	 * Removes the installed package and the package files.
	 */
	public function tear_down() {
		$this->remove_installed_package();

		foreach ( $this->packages as $package ) {
			if ( file_exists( $package ) ) {
				unlink( $package );
			}
		}

		remove_filter( 'filesystem_method', array( $this, 'filter_filesystem_method' ) );

		parent::tear_down();
	}

	/**
	 * Forces the direct filesystem method.
	 *
	 * @return string The filesystem method.
	 */
	public function filter_filesystem_method() {
		return 'direct';
	}

	/**
	 * Tests that a package whose files are not wrapped in a single folder is installed
	 * into a folder named after the package, not after the random working directory.
	 *
	 * @ticket 66671
	 */
	public function test_unwrapped_package_should_be_installed_into_a_folder_named_after_the_package() {
		$upgrader = $this->get_upgrader();
		$source   = $upgrader->unpack_package( $this->create_package( false ), true );

		$this->assertIsString( $source, 'The package was not unpacked.' );
		$this->assertStringStartsWith(
			self::PACKAGE . '-',
			basename( $source ),
			'The working directory should keep its unpredictable name.'
		);

		$result = $upgrader->install_package( $this->get_install_args( $source ) );

		$this->assertIsArray( $result, 'The package was not installed.' );
		$this->assertSame( self::PACKAGE, $result['destination_name'] );
		$this->assertFileExists( WP_PLUGIN_DIR . '/' . self::PACKAGE . '/' . self::PACKAGE . '.php' );
	}

	/**
	 * Tests that installing the same unwrapped package again targets the existing folder,
	 * so the existing "folder exists" handling applies instead of creating a second copy.
	 *
	 * @ticket 66671
	 */
	public function test_unwrapped_package_installed_twice_should_return_folder_exists() {
		$upgrader = $this->get_upgrader();
		$upgrader->install_package( $this->get_install_args( $upgrader->unpack_package( $this->create_package( false ), true ) ) );

		$upgrader = $this->get_upgrader();
		$result   = $upgrader->install_package( $this->get_install_args( $upgrader->unpack_package( $this->create_package( false ), true ) ) );

		$this->assertWPError( $result );
		$this->assertSame( 'folder_exists', $result->get_error_code() );
		$this->assertCount( 1, glob( WP_PLUGIN_DIR . '/' . self::PACKAGE . '*', GLOB_ONLYDIR ), 'A second copy of the package was installed.' );
	}

	/**
	 * Tests that a package wrapped in a single folder is still installed into that folder.
	 *
	 * @ticket 66671
	 */
	public function test_wrapped_package_should_be_installed_into_its_folder() {
		$upgrader = $this->get_upgrader();
		$result   = $upgrader->install_package( $this->get_install_args( $upgrader->unpack_package( $this->create_package( true ), true ) ) );

		$this->assertIsArray( $result, 'The package was not installed.' );
		$this->assertSame( self::PACKAGE, $result['destination_name'] );
	}

	/**
	 * Creates an upgrader with a mocked skin.
	 *
	 * @return WP_Upgrader The upgrader.
	 */
	private function get_upgrader() {
		$upgrader = new WP_Upgrader( $this->createMock( 'WP_Upgrader_Skin' ) );
		$upgrader->generic_strings();

		return $upgrader;
	}

	/**
	 * Gets the arguments to install a package into the plugins directory.
	 *
	 * @param string $source The unpacked package source.
	 * @return array The arguments for WP_Upgrader::install_package().
	 */
	private function get_install_args( $source ) {
		return array(
			'source'            => $source,
			'destination'       => WP_PLUGIN_DIR,
			'clear_destination' => false,
			'clear_working'     => true,
		);
	}

	/**
	 * Creates a package containing a plugin file.
	 *
	 * @param bool $wrapped Whether to wrap the plugin file in a folder named after the package.
	 * @return string The path to the package.
	 */
	private function create_package( $wrapped ) {
		$package = get_temp_dir() . self::PACKAGE . '.zip';
		$file    = self::PACKAGE . '.php';

		if ( $wrapped ) {
			$file = self::PACKAGE . '/' . $file;
		}

		$zip = new ZipArchive();
		$zip->open( $package, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		$zip->addFromString( $file, "<?php\n/*\n * Plugin Name: WP Upgrader Destination Test\n */\n" );
		$zip->close();

		$this->packages[] = $package;

		return $package;
	}

	/**
	 * Removes any installed copy of the test package.
	 */
	private function remove_installed_package() {
		global $wp_filesystem;

		foreach ( glob( WP_PLUGIN_DIR . '/' . self::PACKAGE . '*', GLOB_ONLYDIR ) as $dir ) {
			$wp_filesystem->delete( $dir, true );
		}
	}
}
