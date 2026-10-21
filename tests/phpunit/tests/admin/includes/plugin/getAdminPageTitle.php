<?php

/**
 * Tests for get_admin_page_title().
 *
 * @group admin
 * @group plugins
 *
 * @covers ::get_admin_page_title
 */
class Tests_Admin_Includes_Plugin_GetAdminPageTitle extends WP_UnitTestCase {

	/**
	 * Names of the globals used by the function under test.
	 *
	 * @var string[]
	 */
	const GLOBALS_USED = array(
		'title',
		'menu',
		'submenu',
		'pagenow',
		'typenow',
		'plugin_page',
		'parent_file',
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

	/**
	 * Whether $_GET['page'] was set before the test.
	 *
	 * @var bool
	 */
	private $had_get_page;

	/**
	 * Original $_GET['page'] value.
	 *
	 * @var mixed
	 */
	private $orig_get_page;

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

		$this->had_get_page  = array_key_exists( 'page', $_GET );
		$this->orig_get_page = $this->had_get_page ? $_GET['page'] : null;
		unset( $_GET['page'] );

		$GLOBALS['title']            = '';
		$GLOBALS['plugin_page']      = '';
		$GLOBALS['typenow']          = '';
		$GLOBALS['parent_file']      = '';
		$GLOBALS['admin_page_hooks'] = array();
		$GLOBALS['menu']             = array();
		$GLOBALS['submenu']          = array();
	}

	public function tear_down() {
		foreach ( self::GLOBALS_USED as $name ) {
			if ( array_key_exists( $name, $this->orig_globals ) ) {
				$GLOBALS[ $name ] = $this->orig_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		if ( $this->had_get_page ) {
			$_GET['page'] = $this->orig_get_page;
		} else {
			unset( $_GET['page'] );
		}

		parent::tear_down();
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_existing_title_global() {
		$GLOBALS['title'] = 'Already Set Title';

		$this->assertSame( 'Already Set Title', get_admin_page_title() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_top_level_menu_title_by_pagenow() {
		$GLOBALS['pagenow'] = 'profile.php';
		$GLOBALS['menu']    = array(
			70 => array( 'Profile', 'read', 'profile.php', 'Your Profile', 'menu-top', 'menu-profile', 'dashicons-admin-users' ),
		);

		$this->assertSame( 'Your Profile', get_admin_page_title() );
		$this->assertSame( 'Your Profile', $GLOBALS['title'], 'The $title global should be updated.' );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_menu_name_when_page_title_is_missing() {
		$GLOBALS['pagenow'] = 'profile.php';
		$GLOBALS['menu']    = array(
			70 => array( 'Profile', 'read', 'profile.php' ),
		);

		$this->assertSame( 'Profile', get_admin_page_title() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_submenu_title_for_plugin_page() {
		$GLOBALS['pagenow']     = 'options-general.php';
		$GLOBALS['plugin_page'] = 'my-settings';
		$_GET['page']           = 'my-settings';
		$GLOBALS['menu']        = array(
			80 => array( 'Settings', 'manage_options', 'options-general.php', 'Settings', 'menu-top', 'menu-settings', 'dashicons-admin-settings' ),
		);
		$GLOBALS['submenu']     = array(
			'options-general.php' => array(
				10 => array( 'General', 'manage_options', 'options-general.php', 'General Settings' ),
				50 => array( 'My Settings', 'manage_options', 'my-settings', 'My Settings Page' ),
			),
		);

		$this->assertSame( 'My Settings Page', get_admin_page_title() );
		$this->assertSame( 'My Settings Page', $GLOBALS['title'], 'The $title global should be updated.' );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_submenu_title_for_core_page_by_pagenow() {
		$GLOBALS['pagenow'] = 'options-general.php';
		$GLOBALS['menu']    = array(
			80 => array( 'Settings', 'manage_options', 'options-general.php', 'Settings', 'menu-top', 'menu-settings', 'dashicons-admin-settings' ),
		);
		$GLOBALS['submenu'] = array(
			'options-general.php' => array(
				10 => array( 'General', 'manage_options', 'options-general.php', 'General Settings' ),
			),
		);

		$this->assertSame( 'General Settings', get_admin_page_title() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_top_level_plugin_title_on_admin_php() {
		$GLOBALS['pagenow']     = 'admin.php';
		$GLOBALS['plugin_page'] = 'my-plugin';
		$GLOBALS['menu']        = array(
			25 => array( 'My Plugin', 'manage_options', 'my-plugin', 'My Plugin Title', 'menu-top', 'toplevel_page_my-plugin', 'dashicons-admin-generic' ),
		);
		// Use a distinct submenu slug/title so the expected title must come from the top-level menu fallback.
		$GLOBALS['submenu'] = array(
			'my-plugin' => array(
				0 => array( 'Settings', 'manage_options', 'my-plugin-settings', 'My Plugin Settings' ),
			),
		);

		$this->assertSame( 'My Plugin Title', get_admin_page_title() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_submenu_title_for_post_type_parent() {
		$GLOBALS['pagenow']     = 'edit.php';
		$GLOBALS['typenow']     = 'page';
		$GLOBALS['plugin_page'] = 'my-page-tool';
		$GLOBALS['menu']        = array(
			20 => array( 'Pages', 'edit_pages', 'edit.php?post_type=page', 'Pages', 'menu-top', 'menu-pages', 'dashicons-admin-page' ),
		);
		$GLOBALS['submenu']     = array(
			'edit.php?post_type=page' => array(
				5  => array( 'All Pages', 'edit_pages', 'edit.php?post_type=page', 'Pages' ),
				15 => array( 'My Tool', 'edit_pages', 'my-page-tool', 'Page Tool Title' ),
			),
		);

		$this->assertSame( 'Page Tool Title', get_admin_page_title() );
	}
}
