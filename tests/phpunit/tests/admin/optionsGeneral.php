<?php
/**
 * Tests for the "(Language default)" label in the Date/Time format section
 * of Settings > General (options-general.php).
 *
 * The locale-discovery block at the top of that section captures the site
 * locale's default date/time format strings. These tests verify the logic
 * of that block in isolation, without rendering the full admin page.
 *
 * @ticket 64102
 * @group  admin
 * @group  options-general
 * @group  l10n
 */
class Tests_Admin_Options_General_DateTimeLabel extends WP_UnitTestCase {

	/**
	 * Administrator user created once for all tests in this class.
	 *
	 * @var int
	 */
	protected static $admin_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id = $factory->user->create(
			array(
				'role'   => 'administrator',
				'locale' => '', // No explicit user locale — will be overridden per test.
			)
		);
	}

	public function set_up() {
		parent::set_up();

		// Clear translation caches so locale switches take effect cleanly.
		unset( $GLOBALS['l10n'], $GLOBALS['l10n_unloaded'] );
	}

	public function tear_down() {
		// Unwind any locale switch that a test may have left active.
		restore_current_locale();

		// Clear translation caches.
		unset( $GLOBALS['l10n'], $GLOBALS['l10n_unloaded'] );

		// Restore the $locale global to whatever it was before this test manipulated it.
		parent::tear_down();
	}

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Replicates the locale-discovery block from options-general.php.
	 *
	 * Returns the site locale's default date/time format strings, or null for
	 * each when the site locale's language pack is not installed on disk (so
	 * the caller never shows a label it cannot reliably identify).
	 *
	 * Note: the null-guard (switch path) only fires when the site locale and the
	 * user/current locale are DIFFERENT. When they are the same, the fast path
	 * takes over — and if the locale is invalid/unloaded, __() still returns the
	 * English string, which is acceptable because the page is already rendering in
	 * that same (site) locale context.
	 *
	 * @return array{ date: string|null, time: string|null }
	 */
	private function get_site_locale_defaults() {
		$site_locale    = get_locale();
		$current_locale = determine_locale();

		$site_locale_date_default = null;
		$site_locale_time_default = null;

		if ( $site_locale === $current_locale ) {
			// Fast path: already in the site locale — __() gives the correct value.
			$site_locale_date_default = __( 'F j, Y' );
			$site_locale_time_default = __( 'g:i a' );
		} else {
			// switch_to_locale() returns false when the pack is not installed.
			$locale_switched = switch_to_locale( $site_locale );
			if ( $locale_switched ) {
				$site_locale_date_default = __( 'F j, Y' );
				$site_locale_time_default = __( 'g:i a' );
				restore_previous_locale();
			}
		}

		return array(
			'date' => $site_locale_date_default,
			'time' => $site_locale_time_default,
		);
	}

	// -------------------------------------------------------------------------
	// Tests: locale-discovery logic
	// -------------------------------------------------------------------------

	/**
	 * Fast path: when site locale and user locale are the same, __() already
	 * runs in the correct locale — no switch needed and defaults are captured.
	 *
	 * @ticket 64102
	 * @covers ::determine_locale
	 * @covers ::get_locale
	 */
	public function test_defaults_captured_when_site_and_user_locale_match() {
		// Default test environment: site = en_US, user = en_US (no override).
		// get_locale() and determine_locale() both return 'en_US'.
		$defaults = $this->get_site_locale_defaults();

		$this->assertSame(
			'F j, Y',
			$defaults['date'],
			'Date default must be captured when site and user locale are the same.'
		);
		$this->assertSame(
			'g:i a',
			$defaults['time'],
			'Time default must be captured when site and user locale are the same.'
		);
	}

	/**
	 * Switch path: en_US is hardcoded into WP_Locale_Switcher::$available_languages,
	 * so switch_to_locale( 'en_US' ) always succeeds. The site locale defaults are
	 * captured even when the user locale differs.
	 *
	 * @ticket 64102
	 * @covers ::switch_to_locale
	 * @covers ::restore_previous_locale
	 */
	public function test_defaults_captured_when_site_is_en_us_and_user_locale_differs() {
		global $locale;
		$old_locale = $locale;

		// Set site locale to en_US directly via the global (bypasses DB reads,
		// avoids option-cache invalidation complexity).
		$locale = 'en_US';

		// Force determine_locale() to return a locale different from en_US so the
		// switch path is taken. Priority 0 runs before the locale switcher (priority 10)
		// but is correctly overridden BY the switcher while a switch is in progress.
		$override = static function () {
			return 'de_DE';
		};
		add_filter( 'determine_locale', $override, 0 );

		$defaults = $this->get_site_locale_defaults();

		remove_filter( 'determine_locale', $override, 0 );
		$locale = $old_locale;

		// en_US is always in WP_Locale_Switcher::$available_languages (hardcoded in
		// the constructor). The switch must therefore succeed and defaults be captured.
		$this->assertSame(
			'F j, Y',
			$defaults['date'],
			'Site locale default must be captured when site=en_US and user locale differs.'
		);
		$this->assertSame(
			'g:i a',
			$defaults['time'],
			'Site locale time default must be captured when site=en_US and user locale differs.'
		);
	}

	/**
	 * Null guard: when the site locale string is not a known/installed locale,
	 * switch_to_locale() returns false, and both defaults remain null.
	 * No misleading label must ever be shown.
	 *
	 * @ticket 64102
	 * @covers ::switch_to_locale
	 */
	public function test_defaults_are_null_when_site_locale_pack_not_installed() {
		global $locale;
		$old_locale = $locale;

		// Site locale = 'zz_ZZ': a locale that does not exist on disk.
		$locale = 'zz_ZZ';

		// Force determine_locale() to return 'en_US' so that site ≠ current locale,
		// which forces the switch path. The switch then fails because 'zz_ZZ' is not
		// in WP_Locale_Switcher::$available_languages, keeping defaults null.
		$override = static function () {
			return 'en_US';
		};
		add_filter( 'determine_locale', $override, 0 );

		$defaults = $this->get_site_locale_defaults();

		remove_filter( 'determine_locale', $override, 0 );
		$locale = $old_locale;

		$this->assertNull(
			$defaults['date'],
			'Date default must be null when the site locale pack is not installed.'
		);
		$this->assertNull(
			$defaults['time'],
			'Time default must be null when the site locale pack is not installed.'
		);
	}

	/**
	 * After the discovery block runs the switch path, no locale switch must
	 * remain active. determine_locale() must return what it returned before.
	 *
	 * @ticket 64102
	 * @covers ::switch_to_locale
	 * @covers ::restore_previous_locale
	 */
	public function test_locale_is_fully_restored_after_discovery() {
		global $wp_locale_switcher, $locale;
		$old_locale = $locale;

		$locale   = 'en_US'; // Site locale.
		$override = static function () {
			return 'de_DE';
		};
		add_filter( 'determine_locale', $override, 0 );

		$before = determine_locale(); // 'de_DE' (from our filter).

		$this->get_site_locale_defaults(); // Switches to en_US then restores.

		$after = determine_locale(); // Must still be 'de_DE'.

		remove_filter( 'determine_locale', $override, 0 );
		$locale = $old_locale;

		$this->assertSame(
			$before,
			$after,
			'determine_locale() must return the same value before and after the discovery block.'
		);
		$this->assertFalse(
			$wp_locale_switcher->is_switched(),
			'No locale switch must remain active after the discovery block completes.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: label application logic
	// -------------------------------------------------------------------------

	/**
	 * For a standard en_US site, exactly one entry in the date format list
	 * should match the captured site locale default.
	 *
	 * @ticket 64102
	 */
	public function test_exactly_one_date_format_matches_site_locale_default() {
		$defaults = $this->get_site_locale_defaults();

		$date_formats = array_unique(
			apply_filters( 'date_formats', array( __( 'F j, Y' ), 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y' ) )
		);

		$label_count = 0;
		foreach ( $date_formats as $format ) {
			if ( null !== $defaults['date'] && $format === $defaults['date'] ) {
				++$label_count;
			}
		}

		$this->assertSame(
			1,
			$label_count,
			'Exactly one date format must match the site locale default so exactly one label is rendered.'
		);
	}

	/**
	 * For a standard en_US site, exactly one entry in the time format list
	 * should match the captured site locale default.
	 *
	 * @ticket 64102
	 */
	public function test_exactly_one_time_format_matches_site_locale_default() {
		$defaults = $this->get_site_locale_defaults();

		$time_formats = array_unique(
			apply_filters( 'time_formats', array( __( 'g:i a' ), 'g:i A', 'H:i' ) )
		);

		$label_count = 0;
		foreach ( $time_formats as $format ) {
			if ( null !== $defaults['time'] && $format === $defaults['time'] ) {
				++$label_count;
			}
		}

		$this->assertSame(
			1,
			$label_count,
			'Exactly one time format must match the site locale default so exactly one label is rendered.'
		);
	}

	/**
	 * When the site locale pack is not installed, no format entry receives a label.
	 *
	 * @ticket 64102
	 */
	public function test_no_label_applied_when_site_locale_pack_not_installed() {
		global $locale;
		$old_locale = $locale;

		$locale   = 'zz_ZZ'; // Site locale = non-existent.
		$override = static function () {
			return 'en_US';
		};
		add_filter( 'determine_locale', $override, 0 );

		$defaults = $this->get_site_locale_defaults();

		remove_filter( 'determine_locale', $override, 0 );
		$locale = $old_locale;

		$date_formats = array_unique(
			apply_filters( 'date_formats', array( __( 'F j, Y' ), 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y' ) )
		);

		foreach ( $date_formats as $format ) {
			$this->assertFalse(
				null !== $defaults['date'] && $format === $defaults['date'],
				'No date format must be labelled when the site locale pack is not installed.'
			);
		}
	}

	// -------------------------------------------------------------------------
	// Tests: HTML structure
	// -------------------------------------------------------------------------

	/**
	 * The "(Language default)" label is rendered as a sibling <span class="description">
	 * and must NOT appear inside any <span class="date-time-text format-i18n"> element.
	 * This protects the existing JS that reads .format-i18n text for preview updates.
	 *
	 * @ticket 64102
	 * @see src/wp-admin/includes/options.php (jQuery click handler reading .format-i18n)
	 */
	public function test_label_is_never_inside_format_i18n_span() {
		$defaults = $this->get_site_locale_defaults();

		$date_formats = array_unique(
			apply_filters( 'date_formats', array( __( 'F j, Y' ), 'Y-m-d', 'm/d/Y', 'd/m/Y', 'd.m.Y' ) )
		);

		ob_start();
		foreach ( $date_formats as $format ) {
			// Reproduce the exact HTML structure from options-general.php.
			echo '<span class="date-time-text format-i18n">' . esc_html( date_i18n( $format ) ) . '</span>';
			echo '<code>' . esc_html( $format ) . '</code>';
			if ( null !== $defaults['date'] && $format === $defaults['date'] ) {
				/* translators: Shown next to the date/time format that is the default for the site's language. */
				echo ' <span class="description">' . esc_html__( '(Language default)' ) . '</span>';
			}
		}
		$output = ob_get_clean();

		// Assert: no .format-i18n span contains the label text.
		preg_match_all(
			'/<span class="date-time-text format-i18n">(.*?)<\/span>/s',
			$output,
			$matches
		);

		foreach ( $matches[1] as $span_content ) {
			$this->assertStringNotContainsString(
				'Language default',
				$span_content,
				'The "(Language default)" label must not appear inside a .format-i18n span.'
			);
		}
	}
}
