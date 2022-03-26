<?php
/**
 * Tests for the WP_Upgrader class.
 *
 * @package WordPress
 */

/**
 * Tests for the WP_Upgrader class.
 *
 * @since 6.0.0
 *
 * @ticket 53997
 *
 * @group admin
 * @group upgrade
 *
 * @covers WP_Upgrader
 */
class Tests_Admin_IncludesWpUpgrader extends WP_UnitTestCase {

	/**
	 * Set up test assets before the class.
	 */
	public static function wpSetUpBeforeClass() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	}

	/**
	 * Test that the WP_Upgrader object uses the `$skin`
	 * parameter passed to the constructor, or defaults
	 * to a new `WP_Upgrader_Skin` object.
	 *
	 * @dataProvider data_skins
	 *
	 * @covers WP_Upgrader::__construct
	 *
	 * @param string|null $skin     The skin to use.
	 * @param string      $expected The expected class for the skin.
	 */
	public function test_should_set_skin( $skin, $expected ) {
		if ( $skin ) {
			$upgrader = new WP_Upgrader( new $skin() );
		} else {
			$upgrader = new WP_Upgrader();
		}

		$this->assertSame( $expected, get_class( $upgrader->skin ) );
	}

	/**
	 * Test that connecting to the filesystem fails when
	 * requesting credentials fails.
	 *
	 * @dataProvider data_skins
	 *
	 * @covers WP_Upgrader::fs_connect
	 * @covers WP_Upgrader_Skin::request_filesystem_credentials
	 * @covers ::request_filesystem_credentials
	 *
	 * @param string|null $skin The skin to use.
	 */
	public function test_should_return_false_when_requesting_credentials_fails( $skin ) {
		if ( $skin ) {
			$upgrader = new WP_Upgrader( new $skin() );
		} else {
			$upgrader = new WP_Upgrader();
		}

		add_filter( 'request_filesystem_credentials', '__return_false' );

		$this->assertFalse( $upgrader->fs_connect( array( ABSPATH ) ) );
	}

	/**
	 * Test that connecting to the filesystem fails when
	 * the filesystem cannot be initialized
	 *
	 * @dataProvider data_skins
	 *
	 * @covers WP_Upgrader::fs_connect
	 * @covers ::request_filesystem_credentials
	 * @covers ::WP_Filesystem
	 *
	 * @param string|null $skin The skin to use.
	 */
	public function test_should_return_false_when_the_filesystem_cannot_be_initialized( $skin ) {
		if ( $skin ) {
			$upgrader = new WP_Upgrader( new $skin() );
		} else {
			$upgrader = new WP_Upgrader();
		}

		$this->assertNotFalse(
			$upgrader->skin->request_filesystem_credentials( false, ABSPATH, false ),
			'The connection failed before initializing the filesystem'
		);

		// Force a failure.
		add_filter( 'filesystem_method', '__return_empty_string' );

		// Suppress output.
		$this->setOutputCallback( '__return_null' );

		$this->assertFalse(
			$upgrader->fs_connect( array( ABSPATH ) ),
			'The filesystem was initialized'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_skins() {
		return array(
			'no skin'                     => array(
				'skin'     => null,
				'expected' => 'WP_Upgrader_Skin',
			),
			'WP_Upgrader_Skin'            => array(
				'skin'     => 'WP_Upgrader_Skin',
				'expected' => 'WP_Upgrader_Skin',
			),
			'Plugin_Upgrader_Skin'        => array(
				'skin'     => 'Plugin_Upgrader_Skin',
				'expected' => 'Plugin_Upgrader_Skin',
			),
			'Theme_Upgrader_Skin'         => array(
				'skin'     => 'Theme_Upgrader_Skin',
				'expected' => 'Theme_Upgrader_Skin',
			),
			'Bulk_Upgrader_Skin'          => array(
				'skin'     => 'Bulk_Upgrader_Skin',
				'expected' => 'Bulk_Upgrader_Skin',
			),
			'Bulk_Plugin_Upgrader_Skin'   => array(
				'skin'     => 'Bulk_Plugin_Upgrader_Skin',
				'expected' => 'Bulk_Plugin_Upgrader_Skin',
			),
			'Bulk_Theme_Upgrader_Skin'    => array(
				'skin'     => 'Bulk_Theme_Upgrader_Skin',
				'expected' => 'Bulk_Theme_Upgrader_Skin',
			),
			'Plugin_Installer_Skin'       => array(
				'skin'     => 'Plugin_Installer_Skin',
				'expected' => 'Plugin_Installer_Skin',
			),
			'Theme_Installer_Skin'        => array(
				'skin'     => 'Theme_Installer_Skin',
				'expected' => 'Theme_Installer_Skin',
			),
			'Language_Pack_Upgrader_Skin' => array(
				'skin'     => 'Language_Pack_Upgrader_Skin',
				'expected' => 'Language_Pack_Upgrader_Skin',
			),
			'Automatic_Upgrader_Skin'     => array(
				'skin'     => 'Automatic_Upgrader_Skin',
				'expected' => 'Automatic_Upgrader_Skin',
			),
			'WP_Ajax_Upgrader_Skin'       => array(
				'skin'     => 'WP_Ajax_Upgrader_Skin',
				'expected' => 'WP_Ajax_Upgrader_Skin',
			),
		);
	}

	/**
	 * Test that the skin's `$upgrader` property is set
	 * to the WP_Upgrader object on initialization.
	 *
	 * @covers WP_Upgrader::init
	 * @covers WP_Upgrader_Skin::set_upgrader
	 */
	public function test_should_set_skin_upgrader_to_current_object() {
		$upgrader = new WP_Upgrader();
		$this->assertNull(
			$upgrader->skin->upgrader,
			"The skin's upgrader is not null"
		);

		$upgrader->init();

		$this->assertNotNull(
			$upgrader->skin->upgrader,
			"The skin's upgrader is null"
		);

		$this->assertSame(
			$upgrader,
			$upgrader->skin->upgrader,
			"The skin's upgrader is not the current WP_Upgrader object"
		);
	}

	/**
	 * Test that the generic strings are added on initialization.
	 *
	 * @covers WP_Upgrader::init
	 * @covers WP_Upgrader::generic_strings
	 */
	public function test_should_set_generic_strings() {
		$upgrader = new WP_Upgrader();

		$this->assertEmpty(
			$upgrader->strings,
			'The strings array is not empty'
		);

		$upgrader->init();

		$this->assertNotEmpty(
			$upgrader->strings,
			'The strings array is empty'
		);

		$expected_keys = array(
			'bad_request',
			'fs_unavailable',
			'fs_error',
			'fs_no_root_dir',
			'fs_no_content_dir',
			'fs_no_plugins_dir',
			'fs_no_themes_dir',
			'fs_no_folder',
			'no_package',
			'download_failed',
			'installing_package',
			'no_files',
			'folder_exists',
			'mkdir_failed',
			'incompatible_archive',
			'files_not_writable',
			'maintenance_start',
			'maintenance_end',
		);

		$expected_count = count( $expected_keys );
		$actual_count   = count( $upgrader->strings );
		$more_or_less   = $expected_count < $actual_count ? 'more' : 'less';

		$this->assertSame(
			$expected_count,
			$actual_count,
			"The strings array has $more_or_less items than expected"
		);

		$this->assertSameSetsWithIndex(
			$expected_keys,
			array_keys( $upgrader->strings ),
			'The string keys do not match the expected keys'
		);
	}

	/**
	 * Test that `WP_Upgrader::download_package()` returns early
	 * when the `upgrader_pre_download` filter does not return `false`.
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_filter_upgrader_pre_download() {
		$upgrader = new WP_Upgrader();

		add_filter( 'upgrader_pre_download', '__return_true' );

		$this->assertTrue( $upgrader->download_package( 'a_package' ) );
	}

	/**
	 * Test that `WP_Upgrader::download_package()` returns early
	 * when the package is a local file.
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_return_local_file() {
		$upgrader   = new WP_Upgrader();
		$local_file = ABSPATH . 'readme.html';
		$this->assertSame( $local_file, $upgrader->download_package( $local_file ) );
	}

	/**
	 * Test that `WP_Upgrader::download_package()` returns the correct
	 * `WP_Error` object when the package is empty.
	 *
	 * @covers WP_Upgrader::download_package
	 */
	public function test_download_package_should_return_no_package_error() {
		$upgrader = new WP_Upgrader();
		$upgrader->generic_strings();

		$actual = $upgrader->download_package( '' );

		$this->assertWPError(
			$actual,
			'Did not return a WP Error object'
		);

		$this->assertSame(
			'no_package',
			$actual->get_error_code(),
			'Did not return the correct error code'
		);

		$this->assertSame(
			$upgrader->strings['no_package'],
			$actual->get_error_message(),
			'Did not return the correct error message'
		);
	}

	/**
	 * Test that `WP_Upgrader::flatten_dirlist()` returns an empty array.
	 *
	 * @covers WP_Upgrader::flatten_dirlist
	 */
	public function test_flatten_dirlist_should_return_empty_array() {
		$upgrader = new WP_Upgrader();

		// `WP_Upgrader::flatten_dirlist()` has `protected` access.
		$flatten_dirlist = new ReflectionMethod( $upgrader, 'flatten_dirlist' );
		$flatten_dirlist->setAccessible( true );

		$this->assertSame( array(), $flatten_dirlist->invoke( $upgrader, array() ) );
	}

	/**
	 * Test that `WP_Upgrader::flatten_dirlist()` returns a flattened dirlist.
	 *
	 * @dataProvider data_flatten_dirlist_should_returns_flattened_dirlist
	 *
	 * @covers WP_Upgrader::flatten_dirlist
	 *
	 * @param array $dirlist  The unflattened dirlist.
	 * @param array $expected The expected flattened dirlist.
	 */
	public function test_flatten_dirlist_should_returns_flattened_dirlist( $dirlist, $expected ) {
		$upgrader = new WP_Upgrader();

		// `WP_Upgrader::flatten_dirlist()` has `protected` access.
		$flatten_dirlist = new ReflectionMethod( $upgrader, 'flatten_dirlist' );
		$flatten_dirlist->setAccessible( true );

		$actual = $flatten_dirlist->invoke( $upgrader, $dirlist );

		$this->assertSameSetsWithIndex( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_flatten_dirlist_should_returns_flattened_dirlist() {
		return array(
			'no subdirectories' => array(
				'dirlist'  => array(
					'my_files' => array(
						'file1' => 'file1.txt',
						'file2' => 'file2.txt',
						'file3' => 'file3.txt',
						'file4' => 'file4.txt',
						'file5' => 'file5.txt',
					),
				),
				'expected' => array(
					'my_files' => array(
						'file1' => 'file1.txt',
						'file2' => 'file2.txt',
						'file3' => 'file3.txt',
						'file4' => 'file4.txt',
						'file5' => 'file5.txt',
					),
				),
			),
		);
	}

	/**
	 * Test that `WP_Upgrader::clear_destination()` returns early with `true`
	 * when the destination does not exist.
	 *
	 * @global $wp_filesystem
	 *
	 * @covers WP_Upgrader::clear_destination
	 */
	public function test_clear_destination_should_return_early_with_true() {
		global $wp_filesystem;

		// Requiring these at any other stage causes other tests to fail.
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		$initial_wp_filesystem = $wp_filesystem;

		$wp_filesystem = new WP_Filesystem_Direct( null );

		$upgrader = new WP_Upgrader();
		$this->assertTrue( $upgrader->clear_destination( 'non_existent_destination' ) );

		$wp_filesystem = $initial_wp_filesystem;
	}

	/**
	 * Test that `WP_Upgrader::install_package()` returns a `WP_Error` object
	 * when the source or destination is empty.
	 *
	 * @dataProvider data_install_package_should_return_bad_request_error
	 *
	 * @covers WP_Upgrader::install_package
	 *
	 * @param string $source      The path to the package source.
	 * @param string $destination The path to a folder to install the package in.
	 */
	public function test_install_package_should_return_bad_request_error( $source, $destination ) {
		$upgrader = new WP_Upgrader();
		$upgrader->generic_strings();

		$actual = $upgrader->install_package(
			array(
				'source'      => $source,
				'destination' => $destination,
			)
		);

		$this->assertWPError(
			$actual,
			'Did not return a WP Error object'
		);

		$this->assertSame(
			'bad_request',
			$actual->get_error_code(),
			'Did not return the correct error code'
		);

		$this->assertSame(
			$upgrader->strings['bad_request'],
			$actual->get_error_message(),
			'Did not return the correct error message'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_install_package_should_return_bad_request_error() {
		return array(
			'empty source'      => array(
				'source'      => '',
				'destination' => ABSPATH,
			),
			'empty destination' => array(
				'source'      => ABSPATH,
				'destination' => '',
			),
		);
	}

	/**
	 * Test that `WP_Upgrader::install_package()` returns a `WP_Error`
	 * object when the `upgrader_pre_install` filter returns one.
	 *
	 * @covers WP_Upgrader::install_package
	 */
	public function test_filter_upgrader_pre_install() {
		$upgrader = new WP_Upgrader();

		// Suppress output.
		$this->setOutputCallback( '__return_null' );

		$expected = new WP_Error(
			'upgrader_pre_install_test',
			'The test passed'
		);

		add_filter(
			'upgrader_pre_install',
			static function() use ( $expected ) {
				return $expected;
			}
		);

		$actual = $upgrader->install_package(
			array(
				'source'      => ABSPATH,
				'destination' => ABSPATH,
			)
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test that `WP_Upgrader::release_lock()` removes the supplied lock.
	 *
	 * @covers WP_Upgrader::release_lock
	 */
	public function test_release_lock_should_remove_lock_option() {
		add_option( 'lock_to_release.lock', 1 );
		WP_Upgrader::release_lock( 'lock_to_release' );
		$this->assertFalse( get_option( 'lock_to_release.lock' ) );
	}
}
