<?php

/**
 * @group admin
 */
class Tests_Admin_Theme_Body_Class extends WP_UnitTestCase {
	protected static $admin_user;
	protected $original_theme;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_user = $factory->user->create_and_get( array( 'role' => 'administrator' ) );
	}

	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::$admin_user->ID );
		set_current_screen( 'edit.php' );
		$GLOBALS['admin_body_class'] = '';
		$this->original_theme        = wp_get_theme();
	}

	public function tear_down() {
		$GLOBALS['admin_body_class'] = '';
		switch_theme( $this->original_theme->get_stylesheet() );
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );
		parent::tear_down();
	}

	/**
	 * Test theme-related admin body classes.
	 *
	 * @ticket 19736
	 */
	public function test_theme_admin_body_classes() {
		global $admin_body_class;

		switch_theme( 'block-theme' );
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );

		$admin_body_class .= ' wp-theme-' . sanitize_html_class( get_template() );
		$this->assertStringContainsString( 'wp-theme-block-theme', $admin_body_class, 'Parent theme admin body class not found' );

		$admin_body_class = '';
		switch_theme( 'block-theme-child' );
		do_action( 'setup_theme' );
		do_action( 'after_setup_theme' );

		$admin_body_class .= ' wp-theme-' . sanitize_html_class( get_template() );
		if ( is_child_theme() ) {
			$admin_body_class .= ' wp-child-theme-' . sanitize_html_class( get_stylesheet() );
		}

		$this->assertStringContainsString( 'wp-theme-block-theme', $admin_body_class, 'Parent theme admin body class not found in child theme context' );
		$this->assertStringContainsString( 'wp-child-theme-block-theme-child', $admin_body_class, 'Child theme admin body class not found' );
	}
}
