<?php
/**
 * Tests for {@see wp_should_concatenate_admin_scripts()}.
 *
 * A constant cannot be undefined again, so tests that define `CONCATENATE_SCRIPTS` run in a separate
 * process. `SCRIPT_DEBUG` is already defined by the time a test runs, as when running from
 * `src/`, so tests whose outcome depends on it are skipped when it has the other value.
 *
 * @package WordPress
 * @subpackage Script Loader
 *
 * @group dependencies
 * @group scripts
 *
 * @covers ::wp_should_concatenate_admin_scripts
 */
class Tests_Dependencies_WpShouldConcatenateAdminScripts extends WP_UnitTestCase {

	/**
	 * Value of the `$concatenate_scripts` global before the test, or null if it was not set.
	 *
	 * @var mixed
	 */
	private $original_concatenate_scripts;

	/**
	 * Backs up the `$concatenate_scripts` global, which tests of script_concat_settings() change.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->original_concatenate_scripts = $GLOBALS['concatenate_scripts'] ?? null;
	}

	/**
	 * Restores the `$concatenate_scripts` global.
	 */
	public function tear_down(): void {
		if ( null === $this->original_concatenate_scripts ) {
			unset( $GLOBALS['concatenate_scripts'] );
		} else {
			$GLOBALS['concatenate_scripts'] = $this->original_concatenate_scripts;
		}

		parent::tear_down();
	}

	/**
	 * Tests that `CONCATENATE_SCRIPTS` turns concatenation on, unless `SCRIPT_DEBUG` is on.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_on(): void {
		define( 'CONCATENATE_SCRIPTS', true );

		$this->assertSame( ! SCRIPT_DEBUG, wp_should_concatenate_admin_scripts() );
	}

	/**
	 * Tests that `CONCATENATE_SCRIPTS` turns concatenation off.
	 *
	 * @ticket 57548
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_constant_off(): void {
		define( 'CONCATENATE_SCRIPTS', false );

		$this->assertFalse( wp_should_concatenate_admin_scripts() );
	}

	/**
	 * Tests the default when `CONCATENATE_SCRIPTS` is not defined, which is not to concatenate.
	 *
	 * @ticket 57548
	 */
	public function test_default(): void {
		if ( defined( 'CONCATENATE_SCRIPTS' ) ) {
			$this->markTestSkipped( 'CONCATENATE_SCRIPTS is defined.' );
		}

		$this->assertFalse( wp_should_concatenate_admin_scripts() );
	}

	/**
	 * Tests that the filter overrides the constants.
	 *
	 * @ticket 57548
	 */
	public function test_filter(): void {
		$filter = new MockAction();
		add_filter( 'wp_should_concatenate_admin_scripts', array( $filter, 'filter' ) );

		$this->assertSame( ! SCRIPT_DEBUG && defined( 'CONCATENATE_SCRIPTS' ) && CONCATENATE_SCRIPTS, wp_should_concatenate_admin_scripts() );
		$this->assertSame( 1, $filter->get_call_count() );

		add_filter( 'wp_should_concatenate_admin_scripts', '__return_true', 20 );
		$this->assertTrue( wp_should_concatenate_admin_scripts() );

		add_filter( 'wp_should_concatenate_admin_scripts', '__return_false', 30 );
		$this->assertFalse( wp_should_concatenate_admin_scripts() );
	}

	/**
	 * Tests that script_concat_settings() takes its default from the function on admin screens, and
	 * never concatenates elsewhere.
	 *
	 * @ticket 57548
	 *
	 * @covers ::script_concat_settings
	 */
	public function test_script_concat_settings(): void {
		add_filter( 'wp_should_concatenate_admin_scripts', '__return_true' );

		unset( $GLOBALS['concatenate_scripts'] );
		script_concat_settings();
		$this->assertFalse( $GLOBALS['concatenate_scripts'], 'Expected no concatenation on the front end.' );

		set_current_screen( 'dashboard' );
		unset( $GLOBALS['concatenate_scripts'] );
		script_concat_settings();
		$this->assertTrue( $GLOBALS['concatenate_scripts'], 'Expected concatenation on an admin screen.' );

		add_filter( 'wp_should_concatenate_admin_scripts', '__return_false', 20 );
		unset( $GLOBALS['concatenate_scripts'] );
		script_concat_settings();
		$this->assertFalse( $GLOBALS['concatenate_scripts'], 'Expected the filter to turn concatenation off on an admin screen.' );
	}
}
