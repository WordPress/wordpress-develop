<?php

/**
 * @group l10n
 * @group i18n
 *
 * @covers ::get_locale
 */
class Tests_L10n_GetLocale extends WP_UnitTestCase {
	/**
	 * @global string $locale The current locale.
	 */
	public function test_should_respect_locale_global() {
		global $locale;

		$locale = 'foo';

		$this->assertSame( 'foo', get_locale() );
	}

	/**
	 * @group ms-required
	 *
	 * @global string $locale The current locale.
	 */
	public function test_local_option_should_take_precedence_on_multisite() {
		global $locale;

		$locale = null;

		update_option( 'WPLANG', 'en_GB' );
		update_site_option( 'WPLANG', 'es_ES' );

		$this->assertSame( 'en_GB', get_locale() );
	}

	/**
	 * @group ms-required
	 *
	 * @global string $locale The current locale.
	 */
	public function test_network_option_should_be_fallback_on_multisite() {
		global $locale;

		$locale = null;

		update_site_option( 'WPLANG', 'es_ES' );

		$this->assertSame( 'es_ES', get_locale() );
	}

	/**
	 * @group ms-excluded
	 *
	 * @global string $locale The current locale.
	 */
	public function test_option_should_be_respected_on_nonmultisite() {
		global $locale;

		$locale = null;

		update_option( 'WPLANG', 'es_ES' );

		$this->assertSame( 'es_ES', get_locale() );
	}

	/**
	 * @global string $locale The current locale.
	 */
	public function test_should_fall_back_on_en_US() {
		global $locale;

		$locale = null;

		$this->assertSame( 'en_US', get_locale() );
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
	 * An array stored in the `WPLANG` option falls back to `en_US`. The row is
	 * written directly because `update_option()` rejects a non-string value.
	 *
	 * @ticket 66106
	 *
	 * @group ms-excluded
	 *
	 * @global wpdb   $wpdb   WordPress database abstraction object.
	 * @global string $locale The current locale.
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_option(): void {
		global $locale, $wpdb;

		$locale = null;

		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => 'WPLANG',
				'option_value' => maybe_serialize( array( 'de_DE' ) ),
			)
		);
		wp_cache_flush();

		$this->assertSame( 'en_US', get_locale() );
	}

	/**
	 * @ticket 66106
	 *
	 * @global string $locale The current locale.
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_locale_global(): void {
		global $locale;

		$locale = array( 'de_DE' );

		$this->assertSame( 'en_US', get_locale() );
	}

	/**
	 * @ticket 66106
	 *
	 * @global string $locale The current locale.
	 */
	public function test_should_ignore_a_non_string_locale_filter(): void {
		global $locale;

		$locale = 'es_ES';

		add_filter( 'locale', '__return_empty_array' );

		$this->assertSame( 'es_ES', get_locale() );
	}
}
