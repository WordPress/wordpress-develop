<?php
/**
 * @group plugins
 * @group admin
 */
class Tests_Admin_includesPlugin extends WP_UnitTestCase {
	public static function wpSetUpBeforeClass( $factory ) {
		self::_back_up_mu_plugins();
	}

	public static function wpTearDownAfterClass() {
		self::_restore_mu_plugins();
	}

	function test_get_plugin_data() {
		$data = get_plugin_data( DIR_TESTDATA . '/plugins/hello.php' );

		$default_headers = array(
			'Name'        => 'Hello Dolly',
			'Title'       => '<a href="http://wordpress.org/#">Hello Dolly</a>',
			'PluginURI'   => 'http://wordpress.org/#',
			'Description' => 'This is not just a plugin, it symbolizes the hope and enthusiasm of an entire generation summed up in two words sung most famously by Louis Armstrong: Hello, Dolly. When activated you will randomly see a lyric from Hello, Dolly in the upper right of your admin screen on every page. <cite>By <a href="http://ma.tt/">Matt Mullenweg</a>.</cite>',
			'Author'      => '<a href="http://ma.tt/">Matt Mullenweg</a>',
			'AuthorURI'   => 'http://ma.tt/',
			'Version'     => '1.5.1',
			'TextDomain'  => 'hello-dolly',
			'DomainPath'  => '',
		);

		$this->assertIsArray( $data );

		foreach ( $default_headers as $name => $value ) {
			$this->assertTrue( isset( $data[ $name ] ) );
			$this->assertSame( $value, $data[ $name ] );
		}
	}

	function test_menu_page_url() {
		$current_user = get_current_user_id();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'siteurl', 'http://example.com' );

		// Add some pages.
		add_options_page( 'Test Settings', 'Test Settings', 'manage_options', 'testsettings', 'mt_settings_page' );
		add_management_page( 'Test Tools', 'Test Tools', 'manage_options', 'testtools', 'mt_tools_page' );
		add_menu_page( 'Test Toplevel', 'Test Toplevel', 'manage_options', 'mt-top-level-handle', 'mt_toplevel_page' );
		add_submenu_page( 'mt-top-level-handle', 'Test Sublevel', 'Test Sublevel', 'manage_options', 'sub-page', 'mt_sublevel_page' );
		add_submenu_page( 'mt-top-level-handle', 'Test Sublevel 2', 'Test Sublevel 2', 'manage_options', 'sub-page2', 'mt_sublevel_page2' );
		add_theme_page( 'With Spaces', 'With Spaces', 'manage_options', 'With Spaces', 'mt_tools_page' );
		add_pages_page( 'Appending Query Arg', 'Test Pages', 'edit_pages', 'testpages', 'mt_pages_page' );

		$expected['testsettings']        = 'http://example.com/wp-admin/options-general.php?page=testsettings';
		$expected['testtools']           = 'http://example.com/wp-admin/tools.php?page=testtools';
		$expected['mt-top-level-handle'] = 'http://example.com/wp-admin/admin.php?page=mt-top-level-handle';
		$expected['sub-page']            = 'http://example.com/wp-admin/admin.php?page=sub-page';
		$expected['sub-page2']           = 'http://example.com/wp-admin/admin.php?page=sub-page2';
		$expected['not_registered']      = '';
		$expected['With Spaces']         = 'http://example.com/wp-admin/themes.php?page=With%20Spaces';
		$expected['testpages']           = 'http://example.com/wp-admin/edit.php?post_type=page&#038;page=testpages';

		foreach ( $expected as $name => $value ) {
			$this->assertSame( $value, menu_page_url( $name, false ) );
		}

		wp_set_current_user( $current_user );
	}

	/**
	 * Tests the position parameter.
	 *
	 * @ticket 39776
	 *
	 * @covers ::add_submenu_page
	 *
	 * @param int $position          The position passed for the new item.
	 * @param int $expected_position Where the new item is expected to appear.
	 *
	 * @dataProvider data_submenu_position
	 */
	function test_submenu_position( $position, $expected_position ) {
		global $submenu;
		global $menu;
		$current_user = get_current_user_id();
		$admin_user   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );
		set_current_screen( 'dashboard' );

		// Setup a menu with some items.
		$parent = add_menu_page( 'Test Toplevel', 'Test Toplevel', 'manage_options', 'mt-top-level-handle', 'mt_toplevel_page' );
		foreach ( $this->submenus_to_add() as $menu_to_add ) {
			add_submenu_page( $parent, $menu_to_add[0], $menu_to_add[1], $menu_to_add[2], $menu_to_add[3], $menu_to_add[4] );
		}

		// Insert the new page.
		add_submenu_page( $parent, 'New Page', 'New Page', 'manage_options', 'custom-position', 'custom_pos', $position );
		wp_set_current_user( $current_user );

		// Clean up the temporary user.
		wp_delete_user( $admin_user );
		// Reset current screen.
		set_current_screen( 'front' );

		// Verify the menu was inserted at the expected position.
		$this->assertSame( 'custom-position', $submenu[ $parent ][ $expected_position ][2] );
	}

	/**
	 * Tests the position parameter for menu helper functions.
	 *
	 * @ticket 39776
	 * @group ms-excluded
	 *
	 * @covers ::add_management_page
	 * @covers ::add_options_page
	 * @covers ::add_theme_page
	 * @covers ::add_plugins_page
	 * @covers ::add_users_page
	 * @covers ::add_dashboard_page
	 * @covers ::add_posts_page
	 * @covers ::add_media_page
	 * @covers ::add_links_page
	 * @covers ::add_pages_page
	 * @covers ::add_comments_page
	 *
	 * @param int $position          The position passed for the new item.
	 * @param int $expected_position Where the new item is expected to appear.
	 *
	 * @dataProvider data_submenu_position
	 */
	function test_submenu_helpers_position( $position, $expected_position ) {
		global $submenu;
		global $menu;

		// Reset menus.
		$submenu = array();
		$menu    = array();

		$current_user = get_current_user_id();
		$admin_user   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );
		set_current_screen( 'dashboard' );

		// Test the helper functions that use `add_submenu_page`. Each helper adds to a specific menu root.
		$helper_functions = array(
			array(
				'callback'  => 'add_management_page',
				'menu_root' => 'tools.php',
			),
			array(
				'callback'  => 'add_options_page',
				'menu_root' => 'options-general.php',
			),
			array(
				'callback'  => 'add_theme_page',
				'menu_root' => 'themes.php',
			),
			array(
				'callback'  => 'add_plugins_page',
				'menu_root' => 'plugins.php',
			),
			array(
				'callback'  => 'add_users_page',
				'menu_root' => 'users.php',
			),
			array(
				'callback'  => 'add_dashboard_page',
				'menu_root' => 'index.php',
			),
			array(
				'callback'  => 'add_posts_page',
				'menu_root' => 'edit.php',
			),
			array(
				'callback'  => 'add_media_page',
				'menu_root' => 'upload.php',
			),
			array(
				'callback'  => 'add_links_page',
				'menu_root' => 'link-manager.php',
			),
			array(
				'callback'  => 'add_pages_page',
				'menu_root' => 'edit.php?post_type=page',
			),
			array(
				'callback'  => 'add_comments_page',
				'menu_root' => 'edit-comments.php',
			),
		);

		$actual_positions = array();

		foreach ( $helper_functions as $helper_function ) {

			// Build up demo pages on the menu root.
			foreach ( $this->submenus_to_add() as $menu_to_add ) {
				add_menu_page( $menu_to_add[0], $menu_to_add[1], $menu_to_add[2], $helper_function['menu_root'], $helper_function['menu_root'] );
			}

			$test = 'test_' . $helper_function['callback'];

			// Call the helper function, passing the desired position.
			call_user_func_array( $helper_function['callback'], array( $test, $test, 'manage_options', 'custom-position', '', $position ) );

			$actual_positions[ $test ] = $submenu[ $helper_function['menu_root'] ][ $expected_position ][2];
		}

		// Clean up the temporary user.
		wp_delete_user( $admin_user );
		// Reset current screen.
		set_current_screen( 'front' );

		foreach ( $actual_positions as $test => $actual_position ) {
			// Verify the menu was inserted at the expected position.
			$this->assertSame( 'custom-position', $actual_position, 'Menu not inserted at the expected position with ' . $test );
		}
	}

	/**
	 * Helper to store the menus that are to be added, so getting the length is programmatically done.
	 *
	 * @since 5.3.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type string Page title.
	 *         @type string Menu_title.
	 *         @type string Capability.
	 *         @type string Menu slug.
	 *         @type string Function.
	 *     }
	 * }
	 */
	function submenus_to_add() {
		return array(
			array( 'Submenu Position', 'Submenu Position', 'manage_options', 'sub-page', '' ),
			array( 'Submenu Position 2', 'Submenu Position 2', 'manage_options', 'sub-page2', '' ),
			array( 'Submenu Position 3', 'Submenu Position 3', 'manage_options', 'sub-page3', '' ),
			array( 'Submenu Position 4', 'Submenu Position 4', 'manage_options', 'sub-page4', '' ),
			array( 'Submenu Position 5', 'Submenu Position 5', 'manage_options', 'sub-page5', '' ),
		);
	}

	/**
	 * Data provider for test_submenu_helpers_position().
	 *
	 * @since 5.3.0
	 *
	 * @return array {
	 *     @type array {
	 *         @type int|null Passed position.
	 *         @type int      Expected position.
	 *     }
	 * }
	 */
	function data_submenu_position() {
		$menu_count = count( $this->submenus_to_add() );
		return array(
			array( null, $menu_count ),        // Insert at the end of the menu if null is passed. Default behavior.
			array( 0, 0 ),                     // Insert at the beginning of the menu if 0 is passed.
			array( -1, 0 ),                    // Negative numbers are treated the same as passing 0.
			array( -7, 0 ),                    // Negative numbers are treated the same as passing 0.
			array( 1, 1 ),                     // Insert as the second item.
			array( 3, 3 ),                     // Insert as the 4th item.
			array( $menu_count, $menu_count ), // Numbers equal to the number of items are added at the end.
			array( 123456, $menu_count ),      // Numbers higher than the number of items are added at the end.
		);
	}

	/**
	 * Test that when a submenu has the same slug as a parent item, that it's just appended and ignores the position.
	 *
	 * @ticket 48599
	 */
	function test_position_when_parent_slug_child_slug_are_the_same() {
		global $submenu, $menu;

		// Reset menus.
		$submenu      = array();
		$menu         = array();
		$current_user = get_current_user_id();
		$admin_user   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );
		set_current_screen( 'dashboard' );

		// Setup a menu with some items.
		add_menu_page( 'Main Menu', 'Main Menu', 'manage_options', 'main_slug', 'main_page_callback' );
		add_submenu_page( 'main_slug', 'SubMenu 1', 'SubMenu 1', 'manage_options', 'main_slug', 'submenu_callback_1', 1 );
		add_submenu_page( 'main_slug', 'SubMenu 2', 'SubMenu 2', 'manage_options', 'submenu_page2', 'submenu_callback_2', 2 );
		add_submenu_page( 'main_slug', 'SubMenu 3', 'SubMenu 3', 'manage_options', 'submenu_page3', 'submenu_callback_3', 3 );

		// Clean up the temporary user.
		wp_set_current_user( $current_user );
		wp_delete_user( $admin_user );
		// Reset current screen.
		set_current_screen( 'front' );

		// Verify the menu was inserted at the expected position.
		$this->assertSame( 'main_slug', $submenu['main_slug'][0][2] );
		$this->assertSame( 'submenu_page2', $submenu['main_slug'][1][2] );
		$this->assertSame( 'submenu_page3', $submenu['main_slug'][2][2] );
	}

	/**
	 * Passing a string as position will fail.
	 *
	 * @ticket 48599
	 */
	function test_passing_string_as_position_fires_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'add_submenu_page' );
		global $submenu, $menu;

		// Reset menus.
		$submenu      = array();
		$menu         = array();
		$current_user = get_current_user_id();
		$admin_user   = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_user );
		set_current_screen( 'dashboard' );

		// Setup a menu with some items.
		add_menu_page( 'Main Menu', 'Main Menu', 'manage_options', 'main_slug', 'main_page_callback' );
		add_submenu_page( 'main_slug', 'SubMenu 1', 'SubMenu 1', 'manage_options', 'submenu_page_1', 'submenu_callback_1', '2' );

		// Clean up the temporary user.
		wp_set_current_user( $current_user );
		wp_delete_user( $admin_user );
		// Reset current screen.
		set_current_screen( 'front' );

		// Verify the menu was inserted at the expected position.
		$this->assertSame( 'submenu_page_1', $submenu['main_slug'][1][2] );
	}

	function test_is_plugin_active_true() {
		activate_plugin( 'hello.php' );
		$test = is_plugin_active( 'hello.php' );
		$this->assertTrue( $test );

		deactivate_plugins( 'hello.php' );
	}

	function test_is_plugin_active_false() {
		deactivate_plugins( 'hello.php' );
		$test = is_plugin_active( 'hello.php' );
		$this->assertFalse( $test );
	}

	function test_is_plugin_inactive_true() {
		deactivate_plugins( 'hello.php' );
		$test = is_plugin_inactive( 'hello.php' );
		$this->assertTrue( $test );
	}

	function test_is_plugin_inactive_false() {
		activate_plugin( 'hello.php' );
		$test = is_plugin_inactive( 'hello.php' );
		$this->assertFalse( $test );

		deactivate_plugins( 'hello.php' );
	}

	/**
	 * @covers ::get_plugin_files
	 */
	public function test_get_plugin_files_single() {
		$name = 'hello.php';
		$this->assertSame( array( $name ), get_plugin_files( $name ) );
	}

	/**
	 * @covers ::get_plugin_files
	 */
	public function test_get_plugin_files_folder() {
		$plugin_dir = WP_PLUGIN_DIR . '/list_files_test_plugin';
		@mkdir( $plugin_dir );
		$plugin = $this->_create_plugin( null, 'list_files_test_plugin.php', $plugin_dir );

		$sub_dir = trailingslashit( dirname( $plugin[1] ) ) . 'subdir';
		mkdir( $sub_dir );
		file_put_contents( $sub_dir . '/subfile.php', '<?php // Silence.' );

		$plugin_files = get_plugin_files( plugin_basename( $plugin[1] ) );
		$expected     = array(
			'list_files_test_plugin/list_files_test_plugin.php',
			'list_files_test_plugin/subdir/subfile.php',
		);

		unlink( $sub_dir . '/subfile.php' );
		unlink( $plugin[1] );
		rmdir( $sub_dir );
		rmdir( $plugin_dir );

		$this->assertSame( $expected, $plugin_files );
	}

	/**
	 * @covers ::get_mu_plugins
	 */
	public function test_get_mu_plugins_when_mu_plugins_exists_but_is_empty() {
		mkdir( WPMU_PLUGIN_DIR );

		$mu_plugins = get_mu_plugins();

		rmdir( WPMU_PLUGIN_DIR );

		$this->assertSame( array(), $mu_plugins );
	}

	/**
	 * @covers ::get_mu_plugins
	 */
	public function test_get_mu_plugins_when_mu_plugins_directory_does_not_exist() {
		$this->assertFileNotExists( WPMU_PLUGIN_DIR );
		$this->assertSame( array(), get_mu_plugins() );
	}

	/**
	 * @covers ::get_mu_plugins
	 */
	public function test_get_mu_plugins_should_ignore_index_php_containing_silence_is_golden() {
		mkdir( WPMU_PLUGIN_DIR );

		$this->_create_plugin( '<?php\n//Silence is golden.', 'index.php', WPMU_PLUGIN_DIR );

		$mu_plugins = get_mu_plugins();

		unlink( WPMU_PLUGIN_DIR . '/index.php' );
		rmdir( WPMU_PLUGIN_DIR );

		$this->assertSame( array(), $mu_plugins );
	}

	/**
	 * @covers ::get_mu_plugins
	 */
	public function test_get_mu_plugins_should_not_ignore_index_php_containing_something_other_than_silence_is_golden() {
		mkdir( WPMU_PLUGIN_DIR );

		$this->_create_plugin( '<?php\n//Silence is not golden.', 'index.php', WPMU_PLUGIN_DIR );
		$found = get_mu_plugins();

		// Clean up.
		unlink( WPMU_PLUGIN_DIR . '/index.php' );
		rmdir( WPMU_PLUGIN_DIR );

		$this->assertSame( array( 'index.php' ), array_keys( $found ) );
	}

	/**
	 * @covers ::get_mu_plugins
	 */
	public function test_get_mu_plugins_should_ignore_files_without_php_extensions() {
		mkdir( WPMU_PLUGIN_DIR );

		$this->_create_plugin( '<?php\n//Test', 'foo.php', WPMU_PLUGIN_DIR );
		$this->_create_plugin( '<?php\n//Test 2', 'bar.txt', WPMU_PLUGIN_DIR );
		$found = get_mu_plugins();

		// Clean up.
		unlink( WPMU_PLUGIN_DIR . '/foo.php' );
		unlink( WPMU_PLUGIN_DIR . '/bar.txt' );

		$this->assertSame( array( 'foo.php' ), array_keys( $found ) );
	}

	/**
	 * @covers ::_sort_uname_callback
	 */
	public function test__sort_uname_callback() {
		$this->assertLessThan( 0, _sort_uname_callback( array( 'Name' => 'a' ), array( 'Name' => 'b' ) ) );
		$this->assertGreaterThan( 0, _sort_uname_callback( array( 'Name' => 'c' ), array( 'Name' => 'b' ) ) );
		$this->assertSame( 0, _sort_uname_callback( array( 'Name' => 'a' ), array( 'Name' => 'a' ) ) );
	}

	/**
	 * @covers ::get_dropins
	 */
	public function test_get_dropins_empty() {
		$this->_back_up_drop_ins();

		$this->assertSame( array(), get_dropins() );

		// Clean up.
		$this->_restore_drop_ins();
	}

	/**
	 * @covers ::get_dropins
	 */
	public function test_get_dropins_not_empty() {
		$this->_back_up_drop_ins();

		$p1 = $this->_create_plugin( "<?php\n//Test", 'advanced-cache.php', WP_CONTENT_DIR );
		$p2 = $this->_create_plugin( "<?php\n//Test", 'not-a-dropin.php', WP_CONTENT_DIR );

		$dropins = get_dropins();
		$this->assertSame( array( 'advanced-cache.php' ), array_keys( $dropins ) );

		unlink( $p1[1] );
		unlink( $p2[1] );

		// Clean up.
		$this->_restore_drop_ins();
	}

	/**
	 * @covers ::is_network_only_plugin
	 */
	public function test_is_network_only_plugin_hello() {
		$this->assertFalse( is_network_only_plugin( 'hello.php' ) );
	}

	/**
	 * @covers ::is_network_only_plugin
	 */
	public function test_is_network_only_plugin() {
		$p = $this->_create_plugin( "<?php\n/*\nPlugin Name: test\nNetwork: true" );

		$this->assertTrue( is_network_only_plugin( $p[0] ) );

		unlink( $p[1] );
	}

	/**
	 * @covers ::activate_plugins
	 */
	public function test_activate_plugins_single_no_array() {
		$name = 'hello.php';
		activate_plugins( $name );
		$this->assertTrue( is_plugin_active( $name ) );
		deactivate_plugins( $name );
	}

	/**
	 * @covers ::activate_plugins
	 */
	public function test_activate_plugins_single_array() {
		$name = 'hello.php';
		activate_plugins( array( $name ) );
		$this->assertTrue( is_plugin_active( $name ) );
		deactivate_plugins( $name );
	}

	/**
	 * @covers ::validate_active_plugins
	 */
	public function test_validate_active_plugins_remove_invalid() {
		$plugin = $this->_create_plugin();

		activate_plugin( $plugin[0] );
		unlink( $plugin[1] );

		$result = validate_active_plugins();
		$this->assertTrue( isset( $result[ $plugin[0] ] ) );
	}

	/**
	 * @covers ::validate_active_plugins
	 */
	public function test_validate_active_plugins_empty() {
		$this->assertSame( array(), validate_active_plugins() );
	}

	/**
	 * @covers ::is_uninstallable_plugin
	 */
	public function test_is_uninstallable_plugin() {
		$this->assertFalse( is_uninstallable_plugin( 'hello' ) );
	}

	/**
	 * @covers ::is_uninstallable_plugin
	 */
	public function test_is_uninstallable_plugin_true() {
		$plugin = $this->_create_plugin();

		$uninstallable_plugins               = (array) get_option( 'uninstall_plugins' );
		$uninstallable_plugins[ $plugin[0] ] = true;
		update_option( 'uninstall_plugins', $uninstallable_plugins );

		$this->assertTrue( is_uninstallable_plugin( $plugin[0] ) );

		unset( $uninstallable_plugins[ $plugin[0] ] );
		update_option( 'uninstall_plugins', $uninstallable_plugins );

		unlink( $plugin[1] );
	}

	/**
	 * Generate a plugin.
	 *
	 * This creates a single-file plugin.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 *
	 * @param string $data     Optional. Data for the plugin file. Default is a dummy plugin header.
	 * @param string $filename Optional. Filename for the plugin file. Default is a random string.
	 * @param string $dir_path Optional. Path for directory where the plugin should live.
	 * @return array Two-membered array of filename and full plugin path.
	 */
	private function _create_plugin( $data = "<?php\n/*\nPlugin Name: Test\n*/", $filename = false, $dir_path = false ) {
		if ( false === $filename ) {
			$filename = rand_str() . '.php';
		}

		if ( false === $dir_path ) {
			$dir_path = WP_PLUGIN_DIR;
		}

		$full_name = $dir_path . '/' . wp_unique_filename( $dir_path, $filename );

		$file = fopen( $full_name, 'w' );
		fwrite( $file, $data );
		fclose( $file );

		return array( $filename, $full_name );
	}

	/**
	 * Move existing mu-plugins to wp-content/mu-plugin-backup.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private static function _back_up_mu_plugins() {
		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			$mu_bu_dir = WP_CONTENT_DIR . '/mu-plugin-backup';
			rename( WPMU_PLUGIN_DIR, $mu_bu_dir );
		}
	}

	/**
	 * Restore backed-up mu-plugins.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private static function _restore_mu_plugins() {
		$mu_bu_dir = WP_CONTENT_DIR . '/mu-plugin-backup';

		if ( is_dir( WPMU_PLUGIN_DIR ) ) {
			rmdir( WPMU_PLUGIN_DIR );
		}

		if ( is_dir( $mu_bu_dir ) ) {
			rename( $mu_bu_dir, WPMU_PLUGIN_DIR );
		}
	}

	/**
	 * Move existing drop-ins to wp-content/drop-ins-backup.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private function _back_up_drop_ins() {
		$di_bu_dir = WP_CONTENT_DIR . '/drop-ins-backup';
		if ( ! is_dir( $di_bu_dir ) ) {
			mkdir( $di_bu_dir );
		}

		foreach ( _get_dropins() as $file_to_move => $v ) {
			if ( file_exists( WP_CONTENT_DIR . '/' . $file_to_move ) ) {
				rename( WP_CONTENT_DIR . '/' . $file_to_move, $di_bu_dir . '/' . $file_to_move );
			}
		}
	}

	/**
	 * Restore backed-up drop-ins.
	 *
	 * @since 4.2.0
	 *
	 * @access private
	 */
	private function _restore_drop_ins() {
		$di_bu_dir = WP_CONTENT_DIR . '/drop-ins-backup';

		foreach ( _get_dropins() as $file_to_move => $v ) {
			if ( file_exists( $di_bu_dir . '/' . $file_to_move ) ) {
				rename( $di_bu_dir . '/' . $file_to_move, WP_CONTENT_DIR . '/' . $file_to_move );
			}
		}

		if ( is_dir( $di_bu_dir ) ) {
			rmdir( $di_bu_dir );
		}
	}
}
