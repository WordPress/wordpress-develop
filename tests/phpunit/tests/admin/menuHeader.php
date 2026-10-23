<?php
/**
 * @group admin
 */
class Tests_Admin_MenuHeader extends WP_UnitTestCase {

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_top_level_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu = array(
			array( 'Dashboard', 'read', 'index.php', 'Dashboard', 'menu-top', 'menu-dashboard', 'dashicons-dashboard' ),
		);

		$actual = $this->render_admin_menu( $menu, array() );

		$this->assertStringContainsString( "href='" . admin_url( 'index.php' ) . "'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_submenu_parent_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu    = array(
			array( 'Tools', 'read', 'tools.php', 'Tools', 'menu-top', 'menu-tools', 'dashicons-admin-tools' ),
		);
		$submenu = array(
			'tools.php' => array(
				array( 'Available Tools', 'read', 'tools.php', 'Available Tools' ),
			),
		);

		$actual = $this->render_admin_menu( $menu, $submenu );

		$this->assertStringContainsString( "href='" . admin_url( 'tools.php' ) . "'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_submenu_urls() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu    = array(
			array( 'Posts', 'read', 'edit.php', 'Posts', 'menu-top', 'menu-posts', 'dashicons-admin-post' ),
		);
		$submenu = array(
			'edit.php' => array(
				array( 'All Posts', 'read', 'edit.php', 'All Posts' ),
				array( 'Add New', 'read', 'post-new.php?post_type=post', 'Add New' ),
			),
		);

		$actual = $this->render_admin_menu( $menu, $submenu );

		$this->assertStringContainsString( "href='" . admin_url( 'edit.php' ) . "'", $actual );
		$this->assertStringContainsString( "href='" . admin_url( 'post-new.php?post_type=post' ) . "'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_uses_absolute_plugin_page_url() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_menu_page( 'Test Page', 'Test Page', 'read', 'test-page', '__return_null' );

		global $menu;

		$actual = $this->render_admin_menu( $menu, array(), false );

		$this->assertStringContainsString( "href='" . admin_url( 'admin.php?page=test-page' ) . "'", $actual );
	}

	/**
	 * @ticket 56302
	 */
	public function test_wp_menu_output_preserves_external_urls() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$menu = array(
			array( 'External', 'read', 'https://wordpress.org/news/', 'External', 'menu-top', 'menu-external', 'dashicons-admin-site' ),
		);

		$actual = $this->render_admin_menu( $menu, array() );

		$this->assertStringContainsString( "href='https://wordpress.org/news/'", $actual );
	}

	private function render_admin_menu( $menu, $submenu, $submenu_as_parent = true ) {
		$this->require_menu_header();

		global $self, $parent_file, $submenu_file, $plugin_page, $typenow;

		$globals = $this->backup_globals( array( 'self', 'parent_file', 'submenu_file', 'plugin_page', 'typenow' ) );

		$self         = 'index.php';
		$parent_file  = '';
		$submenu_file = '';
		$plugin_page  = null;
		$typenow      = '';

		ob_start();
		try {
			_wp_menu_output( $menu, $submenu, $submenu_as_parent );
			return ob_get_clean();
		} finally {
			$this->restore_globals( $globals );
		}
	}

	private function require_menu_header() {
		if ( function_exists( '_wp_menu_output' ) ) {
			return;
		}

		global $menu, $submenu, $parent_file, $submenu_file;

		$globals  = $this->backup_globals( array( 'menu', 'submenu', 'parent_file', 'submenu_file' ) );
		$php_self = isset( $_SERVER['PHP_SELF'] ) ? $_SERVER['PHP_SELF'] : null;

		$menu         = array();
		$submenu      = array();
		$parent_file  = '';
		$submenu_file = '';

		$_SERVER['PHP_SELF'] = '/wp-admin/index.php';

		ob_start();
		try {
			require ABSPATH . 'wp-admin/menu-header.php';
			ob_end_clean();
		} finally {
			$this->restore_globals( $globals );

			if ( null === $php_self ) {
				unset( $_SERVER['PHP_SELF'] );
			} else {
				$_SERVER['PHP_SELF'] = $php_self;
			}
		}
	}

	private function backup_globals( $names ) {
		$globals = array();

		foreach ( $names as $name ) {
			$globals[ $name ] = array(
				'exists' => array_key_exists( $name, $GLOBALS ),
				'value'  => array_key_exists( $name, $GLOBALS ) ? $GLOBALS[ $name ] : null,
			);
		}

		return $globals;
	}

	private function restore_globals( $globals ) {
		foreach ( $globals as $name => $global ) {
			if ( $global['exists'] ) {
				$GLOBALS[ $name ] = $global['value'];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
	}
}
