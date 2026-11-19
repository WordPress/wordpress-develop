<?php
/**
 * Tests for {@see script_concat_settings()}.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers ::script_concat_settings
 */
class Tests_Dependencies_ScriptConcatSettings extends WP_UnitTestCase {

	/**
	 * Names of the globals that script_concat_settings() sets.
	 *
	 * @var non-empty-string[]
	 */
	const GLOBAL_NAMES = array( 'concatenate_scripts', 'compress_scripts', 'compress_css' );

	/**
	 * Values of the globals before the test, keyed by name, for those that were set.
	 *
	 * @var array<non-empty-string, mixed>
	 */
	private array $original_globals = array();

	/**
	 * Backs up and unsets the globals, so that script_concat_settings() sets their defaults.
	 */
	public function set_up(): void {
		parent::set_up();

		foreach ( self::GLOBAL_NAMES as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$this->original_globals[ $name ] = $GLOBALS[ $name ];
			}
			unset( $GLOBALS[ $name ] );
		}
	}

	/**
	 * Restores the globals.
	 */
	public function tear_down(): void {
		foreach ( self::GLOBAL_NAMES as $name ) {
			if ( array_key_exists( $name, $this->original_globals ) ) {
				$GLOBALS[ $name ] = $this->original_globals[ $name ];
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}

		parent::tear_down();
	}

	/**
	 * Tests that the `$concatenate_scripts` global defaults to false, even on an admin screen.
	 *
	 * @ticket 57548
	 *
	 * @expectedDeprecated script_concat_settings
	 */
	public function test_concatenate_scripts_global_defaults_to_false(): void {
		set_current_screen( 'dashboard' );

		script_concat_settings();

		$this->assertFalse( $GLOBALS['concatenate_scripts'] );
	}

	/**
	 * Tests that the compression globals default to false, as scripts and styles are not compressed from PHP.
	 *
	 * @ticket 63017
	 *
	 * @expectedDeprecated script_concat_settings
	 */
	public function test_compression_globals_default_to_false(): void {
		// The result of the compression test, which used to turn the compression globals on.
		update_site_option( 'can_compress_scripts', 1 );

		script_concat_settings();

		$this->assertFalse( $GLOBALS['compress_scripts'], 'Expected the $compress_scripts global to default to false.' );
		$this->assertFalse( $GLOBALS['compress_css'], 'Expected the $compress_css global to default to false.' );
	}

	/**
	 * Tests that the obsolete `COMPRESS_SCRIPTS` and `COMPRESS_CSS` constants no longer set the compression globals.
	 *
	 * @ticket 63017
	 *
	 * @expectedDeprecated script_concat_settings
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_compression_constants_are_ignored(): void {
		define( 'COMPRESS_SCRIPTS', true );
		define( 'COMPRESS_CSS', true );
		update_site_option( 'can_compress_scripts', 1 );

		script_concat_settings();

		$this->assertFalse( $GLOBALS['compress_scripts'], 'Expected the COMPRESS_SCRIPTS constant to be ignored.' );
		$this->assertFalse( $GLOBALS['compress_css'], 'Expected the COMPRESS_CSS constant to be ignored.' );
	}

	/**
	 * Tests that values a plugin has already set for the compression globals are kept.
	 *
	 * @ticket 63017
	 *
	 * @expectedDeprecated script_concat_settings
	 */
	public function test_compression_globals_already_set_are_kept(): void {
		$GLOBALS['compress_scripts'] = true;
		$GLOBALS['compress_css']     = true;

		script_concat_settings();

		$this->assertTrue( $GLOBALS['compress_scripts'], 'Expected the $compress_scripts global to be kept.' );
		$this->assertTrue( $GLOBALS['compress_css'], 'Expected the $compress_css global to be kept.' );
	}
}
