<?php

/**
 * Tests for wp_get_active_and_valid_themes().
 *
 * @group load
 * @group themes
 *
 * @covers ::wp_get_active_and_valid_themes
 */
class Tests_Load_WpGetActiveAndValidThemes extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_active_valid_themes_session';

	/**
	 * Original $wp_stylesheet_path global.
	 *
	 * @var mixed
	 */
	private $original_stylesheet_path;

	/**
	 * Original $wp_template_path global.
	 *
	 * @var mixed
	 */
	private $original_template_path;

	/**
	 * Original $pagenow global.
	 *
	 * @var mixed
	 */
	private $original_pagenow;

	/**
	 * Original installing status.
	 *
	 * @var bool
	 */
	private $original_installing;

	/**
	 * Original stylesheet option.
	 *
	 * @var mixed
	 */
	private $original_stylesheet;

	/**
	 * Original template option.
	 *
	 * @var mixed
	 */
	private $original_template;

	/**
	 * Original recovery mode is_active property.
	 *
	 * @var bool
	 */
	private $original_is_active;

	/**
	 * Original recovery mode session_id property.
	 *
	 * @var string
	 */
	private $original_session_id;

	/**
	 * Original $_paused_themes global.
	 *
	 * @var array|null
	 */
	private $original_paused_themes;

	public function set_up() {
		parent::set_up();

		global $wp_stylesheet_path, $wp_template_path, $pagenow;

		$this->original_stylesheet_path = $wp_stylesheet_path ?? null;
		$this->original_template_path   = $wp_template_path ?? null;
		$this->original_pagenow         = $pagenow ?? null;
		$this->original_installing      = wp_installing();
		$this->original_stylesheet      = get_option( 'stylesheet' );
		$this->original_template        = get_option( 'template' );
		$this->original_is_active       = $this->get_recovery_mode_property( 'is_active' );
		$this->original_session_id      = $this->get_recovery_mode_property( 'session_id' );
		$this->original_paused_themes   = $GLOBALS['_paused_themes'] ?? null;

		unset( $GLOBALS['_paused_themes'] );
		wp_installing( false );
	}

	public function tear_down() {
		global $wp_stylesheet_path, $wp_template_path, $pagenow;

		$wp_stylesheet_path = $this->original_stylesheet_path;
		$wp_template_path   = $this->original_template_path;

		if ( null !== $this->original_pagenow ) {
			$pagenow = $this->original_pagenow;
		} else {
			unset( $GLOBALS['pagenow'] );
		}

		wp_installing( $this->original_installing );
		update_option( 'stylesheet', $this->original_stylesheet );
		update_option( 'template', $this->original_template );
		$this->set_recovery_mode_state( $this->original_is_active, $this->original_session_id );
		delete_option( self::TEST_SESSION_ID . '_paused_extensions' );

		if ( null !== $this->original_paused_themes ) {
			$GLOBALS['_paused_themes'] = $this->original_paused_themes;
		} else {
			unset( $GLOBALS['_paused_themes'] );
		}

		remove_all_filters( 'wp_using_themes' );

		parent::tear_down();
	}

	/**
	 * Gets a WP_Recovery_Mode property value.
	 *
	 * @param string $property Property name.
	 * @return mixed Property value.
	 */
	private function get_recovery_mode_property( $property ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		return $reflection->getValue( wp_recovery_mode() );
	}

	/**
	 * Sets WP_Recovery_Mode protected state.
	 *
	 * @param bool   $is_active  Whether recovery mode is active.
	 * @param string $session_id Recovery mode session ID.
	 */
	private function set_recovery_mode_state( $is_active, $session_id ) {
		$recovery_mode = wp_recovery_mode();

		$is_active_property = new ReflectionProperty( $recovery_mode, 'is_active' );
		if ( PHP_VERSION_ID < 80100 ) {
			$is_active_property->setAccessible( true );
		}
		$is_active_property->setValue( $recovery_mode, $is_active );

		$session_id_property = new ReflectionProperty( $recovery_mode, 'session_id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$session_id_property->setAccessible( true );
		}
		$session_id_property->setValue( $recovery_mode, $session_id );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_template_path_for_parent_theme() {
		global $wp_stylesheet_path, $wp_template_path;

		$wp_template_path   = get_theme_root() . '/twentytwentyone';
		$wp_stylesheet_path = $wp_template_path;
		update_option( 'template', 'twentytwentyone' );
		update_option( 'stylesheet', 'twentytwentyone' );

		$this->assertSame( array( $wp_template_path ), wp_get_active_and_valid_themes() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_includes_stylesheet_path_for_child_theme() {
		global $wp_stylesheet_path, $wp_template_path;
		// These are not a real parent/child pair. is_child_theme() only compares the two paths.
		$wp_template_path   = get_theme_root() . '/twentytwentyone';
		$wp_stylesheet_path = get_theme_root() . '/twentytwentyfive';
		update_option( 'template', 'twentytwentyone' );
		update_option( 'stylesheet', 'twentytwentyfive' );

		$this->assertSame(
			array( $wp_stylesheet_path, $wp_template_path ),
			wp_get_active_and_valid_themes()
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_array_while_installing() {
		global $wp_template_path, $pagenow;

		$wp_template_path = get_theme_root() . '/twentytwentyone';
		$pagenow          = 'index.php';
		wp_installing( true );

		$this->assertSame( array(), wp_get_active_and_valid_themes() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_themes_while_installing_on_wp_activate() {
		global $wp_stylesheet_path, $wp_template_path, $pagenow;

		$wp_template_path   = get_theme_root() . '/twentytwentyone';
		$wp_stylesheet_path = $wp_template_path;
		$pagenow            = 'wp-activate.php';
		wp_installing( true );

		$this->assertSame( array( $wp_template_path ), wp_get_active_and_valid_themes() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_skips_paused_themes_in_recovery_mode() {
		global $wp_stylesheet_path, $wp_template_path;

		$wp_template_path   = get_theme_root() . '/twentytwentyone';
		$wp_stylesheet_path = get_theme_root() . '/twentytwentyfive';
		update_option( 'template', 'twentytwentyone' );
		update_option( 'stylesheet', 'twentytwentyfive' );

		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );
		$this->assertTrue(
			wp_paused_themes()->set(
				'twentytwentyfive',
				array(
					'type'    => E_ERROR,
					'file'    => $wp_stylesheet_path . '/functions.php',
					'line'    => 1,
					'message' => 'Fatal error',
				)
			)
		);

		$this->assertSame( array( 1 => $wp_template_path ), wp_get_active_and_valid_themes() );
	}

	/**
	 * @ticket 65819
	 */
	public function test_disables_themes_when_all_are_paused_in_recovery_mode() {
		global $wp_stylesheet_path, $wp_template_path;

		$wp_template_path   = get_theme_root() . '/twentytwentyone';
		$wp_stylesheet_path = $wp_template_path;
		update_option( 'template', 'twentytwentyone' );
		update_option( 'stylesheet', 'twentytwentyone' );

		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );
		$this->assertTrue(
			wp_paused_themes()->set(
				'twentytwentyone',
				array(
					'type'    => E_ERROR,
					'file'    => $wp_template_path . '/functions.php',
					'line'    => 1,
					'message' => 'Fatal error',
				)
			)
		);

		// Begin with themes enabled so disabling them is observable.
		remove_filter( 'wp_using_themes', '__return_false' );
		add_filter( 'wp_using_themes', '__return_true', 0 );
		$this->assertTrue( wp_using_themes() );

		$this->assertSame( array(), wp_get_active_and_valid_themes() );
		$this->assertFalse( wp_using_themes() );
	}
}
