<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::get_locale
 */
class Tests_L10n_GetLocale extends WP_UnitTestCase {
	public function test_should_respect_locale_global() {
		global $locale;
		$old_locale = $locale;

		$locale = 'foo';

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'foo', $found );
	}

	/**
	 * @group ms-required
	 */
	public function test_local_option_should_take_precedence_on_multisite() {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		update_option( 'WPLANG', 'en_GB' );
		update_site_option( 'WPLANG', 'es_ES' );

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_GB', $found );
	}

	/**
	 * @group ms-required
	 */
	public function test_network_option_should_be_fallback_on_multisite() {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		update_site_option( 'WPLANG', 'es_ES' );

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'es_ES', $found );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_option_should_be_respected_on_nonmultisite() {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		update_option( 'WPLANG', 'es_ES' );

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'es_ES', $found );
	}

	public function test_should_fall_back_on_en_US() {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	public function test_should_respect_get_locale_filter() {
		add_filter( 'locale', array( $this, 'filter_get_locale' ) );
		$found = get_locale();
		remove_filter( 'locale', array( $this, 'filter_get_locale' ) );

		$this->assertSame( 'foo', $found );
	}

	public function filter_get_locale() {
		return 'foo';
	}

	/**
	 * Nothing checks the type of the `WPLANG` option on the way out, so a row
	 * written by a direct database query or a migration reaches the return value.
	 *
	 * @ticket 66106
	 *
	 * @group ms-excluded
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_option(): void {
		global $locale, $wpdb;
		$old_locale = $locale;
		$locale     = null;

		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => 'WPLANG',
				'option_value' => maybe_serialize( array( 'de_DE' ) ),
			)
		);
		wp_cache_flush();

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * @ticket 66106
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_locale_global(): void {
		global $locale;
		$old_locale = $locale;
		$locale     = array( 'de_DE' );

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * @ticket 66106
	 */
	public function test_should_ignore_a_non_string_locale_filter(): void {
		global $locale;
		$old_locale = $locale;
		$locale     = 'es_ES';

		add_filter( 'locale', '__return_empty_array' );

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'es_ES', $found );
	}
}
