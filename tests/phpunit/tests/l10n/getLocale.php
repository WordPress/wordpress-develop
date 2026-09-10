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
	 * The `WPLANG` option is untyped storage. sanitize_option() rejects a
	 * non-string on the way in, but nothing checks it on the way out, so a row
	 * written by a direct database query survives to the return value: a
	 * non-empty array passes both `false !== $db_locale` and `empty( $locale )`.
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_stored_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_option( $value ) {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		$this->write_raw_option_row( 'WPLANG', $value );

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * @group ms-required
	 *
	 * @dataProvider data_stored_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_site_option( $value ) {
		global $locale, $wpdb;
		$old_locale = $locale;
		$locale     = null;

		update_site_option( 'WPLANG', 'en_US' );
		$wpdb->update(
			$wpdb->sitemeta,
			array( 'meta_value' => maybe_serialize( $value ) ),
			array(
				'site_id'  => get_current_network_id(),
				'meta_key' => 'WPLANG',
			)
		);
		wp_cache_flush();

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * The `$locale` global short-circuits the function before any of its own
	 * guards run.
	 *
	 * @dataProvider data_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_locale_global( $value ) {
		global $locale;
		$old_locale = $locale;
		$locale     = $value;

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * The `locale` filter result is returned unchecked.
	 *
	 * @dataProvider data_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_should_ignore_a_non_string_locale_filter( $value ) {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		add_filter(
			'locale',
			static function () use ( $value ) {
				return $value;
			}
		);

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * The `locale` filter also runs on the `$locale` global short-circuit path.
	 *
	 * @dataProvider data_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_should_ignore_a_non_string_locale_filter_on_the_global_path( $value ) {
		global $locale;
		$old_locale = $locale;
		$locale     = 'es_ES';

		add_filter(
			'locale',
			static function () use ( $value ) {
				return $value;
			}
		);

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'es_ES', $found );
	}

	/**
	 * The `option_WPLANG` filter runs after the option is read.
	 *
	 * @group ms-excluded
	 *
	 * @dataProvider data_non_string_locale
	 *
	 * @param mixed $value Non-string value.
	 */
	public function test_should_fall_back_on_en_US_for_a_non_string_option_filter( $value ) {
		global $locale;
		$old_locale = $locale;
		$locale     = null;

		add_filter(
			'option_WPLANG',
			static function () use ( $value ) {
				return $value;
			}
		);

		$found  = get_locale();
		$locale = $old_locale;

		$this->assertSame( 'en_US', $found );
	}

	/**
	 * Writes an option row without going through sanitize_option(), the way a
	 * migration or a direct database query would.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Value to store.
	 */
	private function write_raw_option_row( $option, $value ) {
		global $wpdb;

		$wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => $option,
				'option_value' => maybe_serialize( $value ),
			)
		);

		wp_cache_flush();
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_non_string_locale() {
		return array(
			'a list'         => array( array( 'de_DE' ) ),
			'a map'          => array( array( 'locale' => 'de_DE' ) ),
			'an empty array' => array( array() ),
			'an object'      => array( new stdClass() ),
			'an integer'     => array( 1234 ),
			'a float'        => array( 1.5 ),
			'true'           => array( true ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_stored_non_string_locale() {
		// Scalars survive the storage round trip as strings, so only arrays and
		// objects can come back from an option with the wrong type.
		return array(
			'a list'         => array( array( 'de_DE' ) ),
			'a map'          => array( array( 'locale' => 'de_DE' ) ),
			'an empty array' => array( array() ),
			'an object'      => array( new stdClass() ),
		);
	}
}
