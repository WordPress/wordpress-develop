<?php

/**
 * Tests for wp_skip_paused_themes().
 *
 * @group load
 * @group error-protection
 *
 * @covers ::wp_skip_paused_themes
 */
class Tests_Load_WpSkipPausedThemes extends WP_UnitTestCase {

	const TEST_SESSION_ID = 'test_skip_paused_themes_session';

	/**
	 * Original $_paused_themes global.
	 *
	 * @var array|null
	 */
	private $original_paused_themes;

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

	public function set_up() {
		parent::set_up();

		$this->original_paused_themes = $GLOBALS['_paused_themes'] ?? null;
		$this->original_is_active     = $this->get_recovery_mode_property( 'is_active' );
		$this->original_session_id    = $this->get_recovery_mode_property( 'session_id' );

		unset( $GLOBALS['_paused_themes'] );
	}

	public function tear_down() {
		if ( null !== $this->original_paused_themes ) {
			$GLOBALS['_paused_themes'] = $this->original_paused_themes;
		} else {
			unset( $GLOBALS['_paused_themes'] );
		}

		delete_option( self::TEST_SESSION_ID . '_paused_extensions' );
		$this->set_recovery_mode_state( $this->original_is_active, $this->original_session_id );

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
	 * Pauses a theme in recovery mode.
	 *
	 * @param string $theme Theme directory name.
	 * @param array  $error Recorded error.
	 */
	private function pause_theme( $theme, $error ) {
		$this->set_recovery_mode_state( true, self::TEST_SESSION_ID );

		$this->assertTrue( wp_paused_themes()->set( $theme, $error ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_themes_unchanged_when_none_are_paused() {
		$themes = array(
			get_theme_root() . '/twentytwentyone',
			get_theme_root() . '/twentytwentyfive',
		);

		$this->assertSame( $themes, wp_skip_paused_themes( $themes ) );
		$this->assertArrayNotHasKey( '_paused_themes', $GLOBALS );
	}

	/**
	 * @ticket 65819
	 */
	public function test_removes_paused_themes_and_stores_them_for_notices() {
		$error = array(
			'type'    => E_ERROR,
			'file'    => get_theme_root() . '/twentytwentyone/functions.php',
			'line'    => 10,
			'message' => 'Fatal error',
		);

		$this->pause_theme( 'twentytwentyone', $error );

		$parent = get_theme_root() . '/twentytwentyone';
		$child  = get_theme_root() . '/twentytwentyfive';
		$result = wp_skip_paused_themes( array( $parent, $child ) );

		$this->assertSame( array( 1 => $child ), $result );
		$this->assertSame( array( 'twentytwentyone' => $error ), $GLOBALS['_paused_themes'] );
	}

	/**
	 * @ticket 65819
	 */
	public function test_matches_theme_directory_basename() {
		$error = array(
			'type'    => E_ERROR,
			'file'    => get_theme_root() . '/twentytwentyfive/functions.php',
			'line'    => 1,
			'message' => 'Fatal error',
		);

		$this->pause_theme( 'twentytwentyfive', $error );

		$parent = get_theme_root() . '/twentytwentyone';
		$child  = get_theme_root() . '/twentytwentyfive';
		$result = wp_skip_paused_themes( array( $parent, $child ) );

		$this->assertSame( array( $parent ), $result );
		$this->assertSame( array( 'twentytwentyfive' => $error ), $GLOBALS['_paused_themes'] );
	}
}
