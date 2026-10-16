<?php

/**
 * Tests for get_admin_page_parent(), get_plugin_page_hookname() and get_plugin_page_hook().
 *
 * @group admin
 * @group plugins
 */
class Tests_Admin_Includes_Plugin_GetAdminPageParent extends WP_UnitTestCase {

	/**
	 * Names of the globals used by the functions under test.
	 *
	 * @var string[]
	 */
	const GLOBALS_USED = array(
		'parent_file',
		'menu',
		'submenu',
		'pagenow',
		'typenow',
		'plugin_page',
		'admin_page_hooks',
		'_wp_real_parent_file',
		'_wp_menu_nopriv',
		'_wp_submenu_nopriv',
	);

	/**
	 * Original values of the globals that were set before the test, keyed by name.
	 *
	 * @var array
	 */
	private $orig_globals = array();

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$this->orig_globals = array();

		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->orig_globals[ $name ] = $GLOBALS[ $name ];
			}

			unset( $GLOBALS[ $name ] );
		}

		$GLOBALS['pagenow'] = 'index.php';
		$GLOBALS['menu']    = array(
			2  => array( 'Dashboard', 'read', 'index.php', '', 'menu-top', 'menu-dashboard', 'dashicons-dashboard' ),
			25 => array( 'My Plugin', 'manage_options', 'my-plugin', 'My Plugin', 'menu-top', 'toplevel_page_my-plugin', 'dashicons-admin-generic' ),
		);
		$GLOBALS['submenu'] = array(
			'edit.php'                => array(
				5 => array( 'All Posts', 'edit_posts', 'edit.php' ),
			),
			'edit.php?post_type=page' => array(
				5 => array( 'All Pages', 'edit_pages', 'edit.php?post_type=page' ),
			),
			'options-general.php'     => array(
				10 => array( 'General', 'manage_options', 'options-general.php' ),
				50 => array( 'My Settings', 'manage_options', 'my-settings' ),
			),
		);
	}

	public function tear_down() {
		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $this->orig_globals ) ) {
				$GLOBALS[ $name ] = $this->orig_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that an explicit parent page is returned, mapped to its real parent file when there is one.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_admin_page_parent
	 *
	 * @dataProvider data_explicit_parent_pages
	 *
	 * @param string $parent_page Parent page passed to the function.
	 * @param string $expected    Expected parent.
	 */
	public function test_get_admin_page_parent_returns_explicit_parent_page( $parent_page, $expected ) {
		$GLOBALS['_wp_real_parent_file'] = array( 'old-parent.php' => 'new-parent.php' );
		$GLOBALS['parent_file']          = 'unchanged.php';

		$this->assertSame( $expected, get_admin_page_parent( $parent_page ) );
		$this->assertSame( 'unchanged.php', $GLOBALS['parent_file'], 'The $parent_file global should not change.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_explicit_parent_pages() {
		return array(
			'core parent page'            => array( 'options-general.php', 'options-general.php' ),
			'plugin parent page'          => array( 'my-plugin', 'my-plugin' ),
			'parent page that is unknown' => array( 'does-not-exist', 'does-not-exist' ),
			'parent with a real parent'   => array( 'old-parent.php', 'new-parent.php' ),
		);
	}

	/**
	 * Tests the parent detected from the current request.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_admin_page_parent
	 *
	 * @dataProvider data_detected_parent_pages
	 *
	 * @param array  $globals     Globals describing the current request, keyed by name.
	 * @param string $parent_page Parent page passed to the function.
	 * @param string $expected    Expected parent.
	 * @param string $parent_file Expected value of the $parent_file global.
	 */
	public function test_get_admin_page_parent_detects_parent_of_current_page( $globals, $parent_page, $expected, $parent_file ) {
		foreach ( $globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		$this->assertSame( $expected, get_admin_page_parent( $parent_page ) );
		$this->assertSame( $parent_file, $GLOBALS['parent_file'], 'The $parent_file global should be set to the detected parent.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_detected_parent_pages() {
		return array(
			'top-level plugin page'                    => array(
				array(
					'pagenow'     => 'admin.php',
					'plugin_page' => 'my-plugin',
				),
				'',
				'my-plugin',
				'my-plugin',
			),
			'top-level plugin page, admin.php parent'  => array(
				array(
					'pagenow'     => 'admin.php',
					'plugin_page' => 'my-plugin',
				),
				'admin.php',
				'my-plugin',
				'my-plugin',
			),
			'top-level plugin page with a real parent' => array(
				array(
					'pagenow'              => 'admin.php',
					'plugin_page'          => 'my-plugin',
					'_wp_real_parent_file' => array( 'my-plugin' => 'real-parent' ),
				),
				'',
				'real-parent',
				'my-plugin',
			),
			'top-level page without privileges'        => array(
				array(
					'pagenow'         => 'admin.php',
					'plugin_page'     => 'restricted-plugin',
					'_wp_menu_nopriv' => array( 'restricted-plugin' => true ),
				),
				'',
				'restricted-plugin',
				'restricted-plugin',
			),
			'submenu page without privileges'          => array(
				array(
					'pagenow'            => 'tools.php',
					'plugin_page'        => 'restricted-tool',
					'_wp_submenu_nopriv' => array( 'tools.php' => array( 'restricted-tool' => true ) ),
				),
				'',
				'tools.php',
				'tools.php',
			),
			'core submenu page'                        => array(
				array( 'pagenow' => 'options-general.php' ),
				'',
				'options-general.php',
				'options-general.php',
			),
			'post type submenu page'                   => array(
				array(
					'pagenow' => 'edit.php',
					'typenow' => 'page',
				),
				'',
				'edit.php?post_type=page',
				'edit.php?post_type=page',
			),
			'plugin submenu page'                      => array(
				array(
					'pagenow'     => 'admin.php',
					'plugin_page' => 'my-settings',
				),
				'',
				'options-general.php',
				'options-general.php',
			),
			'plugin submenu page with a real parent'   => array(
				array(
					'pagenow'              => 'admin.php',
					'plugin_page'          => 'my-settings',
					'_wp_real_parent_file' => array( 'options-general.php' => 'real-options.php' ),
				),
				'',
				'real-options.php',
				'real-options.php',
			),
		);
	}

	/**
	 * Tests that an empty string is returned when the current page has no parent.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_admin_page_parent
	 *
	 * @dataProvider data_pages_without_parent
	 *
	 * @param array $globals Globals describing the current request, keyed by name.
	 */
	public function test_get_admin_page_parent_returns_empty_string_without_parent( $globals ) {
		foreach ( $globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		$this->assertSame( '', get_admin_page_parent() );
		$this->assertSame( '', $GLOBALS['parent_file'], 'The $parent_file global should be set to an empty string.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_pages_without_parent() {
		return array(
			'page that is not in a submenu' => array( array( 'pagenow' => 'profile.php' ) ),
			'unknown plugin page'           => array(
				array(
					'pagenow'     => 'admin.php',
					'plugin_page' => 'does-not-exist',
				),
			),
			'unknown post type'             => array(
				array(
					'pagenow' => 'edit.php',
					'typenow' => 'book',
				),
			),
			'no menus'                      => array(
				array(
					'pagenow' => 'options-general.php',
					'menu'    => array(),
					'submenu' => array(),
				),
			),
		);
	}

	/**
	 * Tests the hook name of a plugin page.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_plugin_page_hookname
	 *
	 * @dataProvider data_get_plugin_page_hookname
	 *
	 * @param string $plugin_page Plugin page slug.
	 * @param string $parent_page Parent page slug.
	 * @param string $expected    Expected hook name.
	 */
	public function test_get_plugin_page_hookname( $plugin_page, $parent_page, $expected ) {
		$GLOBALS['admin_page_hooks'] = array(
			'my-plugin'           => 'my-plugin',
			'options-general.php' => 'settings',
			'renamed-plugin'      => 'custom-prefix',
		);

		$this->assertSame( $expected, get_plugin_page_hookname( $plugin_page, $parent_page ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_get_plugin_page_hookname() {
		return array(
			'top-level page'                          => array( 'my-plugin', '', 'toplevel_page_my-plugin' ),
			'top-level page with an admin.php parent' => array( 'my-plugin', 'admin.php', 'toplevel_page_my-plugin' ),
			'top-level page with another parent'      => array( 'my-plugin', 'options-general.php', 'toplevel_page_my-plugin' ),
			'submenu page of a core menu'             => array( 'my-settings', 'options-general.php', 'settings_page_my-settings' ),
			'submenu page of a plugin menu'           => array( 'my-subpage', 'my-plugin', 'my-plugin_page_my-subpage' ),
			'submenu page of a renamed plugin menu'   => array( 'my-subpage', 'renamed-plugin', 'custom-prefix_page_my-subpage' ),
			'submenu page of an unknown parent'       => array( 'my-subpage', 'does-not-exist', 'admin_page_my-subpage' ),
			'page without a parent'                   => array( 'my-subpage', '', 'admin_page_my-subpage' ),
			'page with an admin.php parent'           => array( 'my-subpage', 'admin.php', 'admin_page_my-subpage' ),
			'file extension is removed'               => array( 'my-plugin/settings.php', 'options-general.php', 'settings_page_my-plugin/settings' ),
		);
	}

	/**
	 * Tests that the parent of the current page is used when no parent is passed.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_plugin_page_hookname
	 */
	public function test_get_plugin_page_hookname_uses_parent_of_current_page() {
		$GLOBALS['admin_page_hooks'] = array( 'options-general.php' => 'settings' );
		$GLOBALS['pagenow']          = 'admin.php';
		$GLOBALS['plugin_page']      = 'my-settings';

		$this->assertSame( 'settings_page_my-settings', get_plugin_page_hookname( 'my-settings', '' ) );
	}

	/**
	 * Tests that the hook name is only returned when a callback is hooked to it.
	 *
	 * @ticket 65819
	 *
	 * @covers ::get_plugin_page_hook
	 */
	public function test_get_plugin_page_hook_requires_a_hooked_callback() {
		$GLOBALS['admin_page_hooks'] = array( 'options-general.php' => 'settings' );

		$this->assertNull( get_plugin_page_hook( 'my-settings', 'options-general.php' ), 'Null should be returned without a hooked callback.' );

		add_action( 'settings_page_my-settings', '__return_null' );

		$this->assertSame( 'settings_page_my-settings', get_plugin_page_hook( 'my-settings', 'options-general.php' ), 'The hook name should be returned with a hooked callback.' );
		$this->assertNull( get_plugin_page_hook( 'other-settings', 'options-general.php' ), 'Null should be returned for a page without a hooked callback.' );
	}
}
