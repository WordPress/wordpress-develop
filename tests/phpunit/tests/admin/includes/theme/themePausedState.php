<?php

/**
 * Tests for is_theme_paused(), wp_get_theme_error(), and resume_theme().
 *
 * @group admin
 * @group themes
 * @group error-protection
 */
class Tests_Admin_Includes_Theme_ThemePausedState extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_theme_paused_state_session';

	/**
	 * Whether the $_paused_themes global was set before the test.
	 *
	 * @var bool
	 */
	private $had_paused_themes;

	/**
	 * Original $_paused_themes global value.
	 *
	 * @var mixed
	 */
	private $orig_paused_themes;

	/**
	 * Original $wp_stylesheet_path global value.
	 *
	 * @var mixed
	 */
	private $orig_stylesheet_path;

	/**
	 * Original $wp_template_path global value.
	 *
	 * @var mixed
	 */
	private $orig_template_path;

	/**
	 * Original is_active property of the recovery mode singleton.
	 *
	 * @var bool
	 */
	private $orig_is_active;

	/**
	 * Original session_id property of the recovery mode singleton.
	 *
	 * @var string
	 */
	private $orig_session_id;

	public function set_up() {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/theme.php';

		$this->had_paused_themes    = array_key_exists( '_paused_themes', $GLOBALS );
		$this->orig_paused_themes   = $this->had_paused_themes ? $GLOBALS['_paused_themes'] : null;
		$this->orig_stylesheet_path = $GLOBALS['wp_stylesheet_path'];
		$this->orig_template_path   = $GLOBALS['wp_template_path'];
		$this->orig_is_active       = $this->get_recovery_mode_property( 'is_active' );
		$this->orig_session_id      = $this->get_recovery_mode_property( 'session_id' );
	}

	public function tear_down() {
		if ( $this->had_paused_themes ) {
			$GLOBALS['_paused_themes'] = $this->orig_paused_themes;
		} else {
			unset( $GLOBALS['_paused_themes'] );
		}

		$GLOBALS['wp_stylesheet_path'] = $this->orig_stylesheet_path;
		$GLOBALS['wp_template_path']   = $this->orig_template_path;

		$this->set_recovery_mode_property( 'is_active', $this->orig_is_active );
		$this->set_recovery_mode_property( 'session_id', $this->orig_session_id );

		parent::tear_down();
	}

	/**
	 * Gets a private property of the recovery mode singleton.
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
	 * Sets a private property of the recovery mode singleton.
	 *
	 * @param string $property Property name.
	 * @param mixed  $value    Property value.
	 */
	private function set_recovery_mode_property( $property, $value ) {
		$reflection = new ReflectionProperty( wp_recovery_mode(), $property );
		if ( PHP_VERSION_ID < 80100 ) {
			$reflection->setAccessible( true );
		}

		$reflection->setValue( wp_recovery_mode(), $value );
	}

	/**
	 * Puts the recovery mode singleton into an active session.
	 */
	private function activate_recovery_mode() {
		$this->set_recovery_mode_property( 'is_active', true );
		$this->set_recovery_mode_property( 'session_id', self::TEST_SESSION_ID );
	}

	/**
	 * Makes a child theme and its parent theme the active themes.
	 */
	private function activate_child_and_parent_theme() {
		add_filter(
			'stylesheet',
			static function () {
				return 'active-child';
			}
		);
		add_filter(
			'template',
			static function () {
				return 'active-parent';
			}
		);
	}

	/**
	 * Tests that is_theme_paused() returns false when the $_paused_themes global is not set.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_theme_paused
	 */
	public function test_is_theme_paused_returns_false_when_global_not_set() {
		$this->activate_child_and_parent_theme();

		unset( $GLOBALS['_paused_themes'] );

		$this->assertFalse( is_theme_paused( 'active-child' ) );
	}

	/**
	 * Tests is_theme_paused() for active and inactive themes.
	 *
	 * @ticket 65819
	 *
	 * @covers ::is_theme_paused
	 *
	 * @dataProvider data_is_theme_paused
	 *
	 * @param array  $paused_themes Value of the $_paused_themes global.
	 * @param string $theme         Theme directory name to check.
	 * @param bool   $expected      Expected result.
	 */
	public function test_is_theme_paused( $paused_themes, $theme, $expected ) {
		$this->activate_child_and_parent_theme();

		$GLOBALS['_paused_themes'] = $paused_themes;

		$this->assertSame( $expected, is_theme_paused( $theme ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_is_theme_paused() {
		$error = array(
			'type'    => E_ERROR,
			'message' => 'Fatal error',
		);

		return array(
			'paused active stylesheet'        => array( array( 'active-child' => $error ), 'active-child', true ),
			'paused active template'          => array( array( 'active-parent' => $error ), 'active-parent', true ),
			'paused with an empty error'      => array( array( 'active-child' => array() ), 'active-child', true ),
			'paused with a null error'        => array( array( 'active-child' => null ), 'active-child', true ),
			'active theme that is not paused' => array( array( 'active-parent' => $error ), 'active-child', false ),
			'no paused themes'                => array( array(), 'active-child', false ),
			'paused inactive theme'           => array( array( 'inactive-theme' => $error ), 'inactive-theme', false ),
			'empty theme name'                => array( array( '' => $error ), '', false ),
		);
	}

	/**
	 * Tests that wp_get_theme_error() returns false when the $_paused_themes global is not set.
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_get_theme_error
	 */
	public function test_wp_get_theme_error_returns_false_when_global_not_set() {
		unset( $GLOBALS['_paused_themes'] );

		$this->assertFalse( wp_get_theme_error( 'active-child' ) );
	}

	/**
	 * Tests the error returned by wp_get_theme_error().
	 *
	 * @ticket 65819
	 *
	 * @covers ::wp_get_theme_error
	 *
	 * @dataProvider data_wp_get_theme_error
	 *
	 * @param array  $paused_themes Value of the $_paused_themes global.
	 * @param string $theme         Theme directory name to check.
	 * @param mixed  $expected      Expected result.
	 */
	public function test_wp_get_theme_error( $paused_themes, $theme, $expected ) {
		$this->activate_child_and_parent_theme();

		$GLOBALS['_paused_themes'] = $paused_themes;

		$this->assertSame( $expected, wp_get_theme_error( $theme ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_get_theme_error() {
		$error = array(
			'type'    => E_ERROR,
			'file'    => '/srv/www/wp-content/themes/active-child/functions.php',
			'line'    => 12,
			'message' => 'Fatal error',
		);

		return array(
			'paused active theme'      => array( array( 'active-child' => $error ), 'active-child', $error ),
			'paused inactive theme'    => array( array( 'inactive-theme' => $error ), 'inactive-theme', $error ),
			'empty error'              => array( array( 'active-child' => array() ), 'active-child', array() ),
			'null error'               => array( array( 'active-child' => null ), 'active-child', null ),
			'theme that is not paused' => array( array( 'active-parent' => $error ), 'active-child', false ),
			'no paused themes'         => array( array(), 'active-child', false ),
		);
	}

	/**
	 * Tests that resume_theme() removes the stored error of a paused theme.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_theme
	 */
	public function test_resume_theme_removes_paused_theme() {
		$this->activate_recovery_mode();

		$other_error = array( 'message' => 'Other theme error' );

		wp_paused_themes()->set( 'my-theme', array( 'message' => 'Theme error' ) );
		wp_paused_themes()->set( 'other-theme', $other_error );

		$this->assertTrue( resume_theme( 'my-theme' ) );
		$this->assertSame( array( 'other-theme' => $other_error ), wp_paused_themes()->get_all() );
	}

	/**
	 * Tests that resume_theme() only uses the first path segment as the theme.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_theme
	 */
	public function test_resume_theme_uses_first_path_segment() {
		$this->activate_recovery_mode();

		wp_paused_themes()->set( 'my-theme', array( 'message' => 'Theme error' ) );

		$this->assertTrue( resume_theme( 'my-theme/functions.php' ) );
		$this->assertSame( array(), wp_paused_themes()->get_all() );
	}

	/**
	 * Tests that resume_theme() keeps other paused themes when the theme has no stored error.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_theme
	 */
	public function test_resume_theme_keeps_other_paused_themes_when_theme_is_not_paused() {
		$this->activate_recovery_mode();

		$other_error = array( 'message' => 'Other theme error' );

		wp_paused_themes()->set( 'other-theme', $other_error );

		$this->assertTrue( resume_theme( 'my-theme' ) );
		$this->assertSame( array( 'other-theme' => $other_error ), wp_paused_themes()->get_all() );
	}

	/**
	 * Tests that resume_theme() returns an error outside of recovery mode.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_theme
	 */
	public function test_resume_theme_returns_error_when_recovery_mode_is_not_active() {
		$this->set_recovery_mode_property( 'is_active', false );
		$this->set_recovery_mode_property( 'session_id', '' );

		$result = resume_theme( 'my-theme' );

		$this->assertWPError( $result );
		$this->assertSame( 'could_not_resume_theme', $result->get_error_code() );
	}

	/**
	 * Tests that resume_theme() does not redirect when the theme is not the active theme.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_theme
	 */
	public function test_resume_theme_does_not_redirect_for_inactive_theme() {
		$GLOBALS['wp_stylesheet_path'] = '/srv/www/wp-content/themes/active-child';
		$GLOBALS['wp_template_path']   = '/srv/www/wp-content/themes/active-parent';

		$this->activate_recovery_mode();

		wp_paused_themes()->set( 'my-theme', array( 'message' => 'Theme error' ) );

		$redirect = new MockAction();
		add_filter( 'wp_redirect', array( $redirect, 'filter' ) );

		$this->assertTrue( resume_theme( 'my-theme', 'http://example.org/wp-admin/themes.php' ) );
		$this->assertSame( 0, $redirect->get_call_count(), 'No redirect should happen.' );
		$this->assertSame( array(), wp_paused_themes()->get_all() );
	}

	/**
	 * Tests that resume_theme() redirects with an error nonce and loads the theme's functions.php file.
	 *
	 * @ticket 65819
	 *
	 * @covers ::resume_theme
	 *
	 * @dataProvider data_resume_theme_redirects
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param string $stylesheet_path Value of the $wp_stylesheet_path global.
	 * @param string $template_path   Value of the $wp_template_path global.
	 */
	public function test_resume_theme_redirects_and_loads_functions_file( $stylesheet_path, $template_path ) {
		$GLOBALS['wp_stylesheet_path'] = DIR_TESTDATA . $stylesheet_path;
		$GLOBALS['wp_template_path']   = DIR_TESTDATA . $template_path;

		$this->activate_recovery_mode();

		wp_paused_themes()->set( 'sandbox', array( 'message' => 'Theme error' ) );

		$redirect = new MockAction();
		add_filter( 'wp_redirect', array( $redirect, 'filter' ) );

		$result = resume_theme( 'sandbox', 'http://example.org/wp-admin/themes.php?resumed=true' );

		// The sandbox theme's functions.php file prints its path, which resume_theme() discards.
		$output = ob_get_clean();

		$this->assertTrue( $result, 'The theme should be resumed.' );
		$this->assertSame( '', $output, 'Output of the functions.php file should be discarded.' );
		$this->assertTrue( WP_SANDBOX_SCRAPING, 'The functions.php file should be loaded in sandbox scraping mode.' );
		$this->assertSame( array(), wp_paused_themes()->get_all(), 'The stored error should be removed.' );

		$this->assertSame( 1, $redirect->get_call_count(), 'One redirect should happen.' );

		$location = $redirect->get_args()[0][0];
		$query    = array();
		wp_parse_str( (string) wp_parse_url( $location, PHP_URL_QUERY ), $query );

		$this->assertSame( array( 'resumed', '_error_nonce' ), array_keys( $query ), 'The redirect should keep its query arguments and add the nonce.' );
		$this->assertSame( 'http://example.org/wp-admin/themes.php?resumed=true&_error_nonce=' . $query['_error_nonce'], $location );
		$this->assertSame( 1, wp_verify_nonce( $query['_error_nonce'], 'theme-resume-error_sandbox' ), 'The nonce should be tied to the theme.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_resume_theme_redirects() {
		return array(
			'theme is the active stylesheet' => array( '/themedir1/sandbox', '/themedir1/default' ),
			'theme is the active template'   => array( '/themedir1/default', '/themedir1/sandbox' ),
		);
	}
}
