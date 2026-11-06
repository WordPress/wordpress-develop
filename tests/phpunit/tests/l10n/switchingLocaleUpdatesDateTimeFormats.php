<?php
/**
 * @ticket 36259
 * @group l10n
 * @group i18n
 */
class Tests_I18n_SwitchingLocaleUpdatesDateTimeFormats extends WP_UnitTestCase {
	/**
	 * Original locale before tests.
	 *
	 * @var string
	 */
	private $orig_locale;

	/**
	 * Original date format.
	 *
	 * @var string
	 */
	private $orig_date_format;

	/**
	 * Original time format.
	 *
	 * @var string
	 */
	private $orig_time_format;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->orig_locale      = get_locale();
		$this->orig_date_format = get_option( 'date_format' );
		$this->orig_time_format = get_option( 'time_format' );

		if ( 'en_US' !== $this->orig_locale ) {
			switch_to_locale( 'en_US' );
		}

		update_option( 'date_format', 'F j, Y' );
		update_option( 'time_format', 'g:i a' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down() {
		switch_to_locale( $this->orig_locale );
		update_option( 'date_format', $this->orig_date_format );
		update_option( 'time_format', $this->orig_time_format );

		parent::tear_down();
	}

	/**
	 * Mock the translation function for testing.
	 *
	 * @param string $locale The locale to mock translations for.
	 */
	private function mock_translations_for_locale( $locale ) {
		global $l10n;

		$translations = new MO();

		if ( 'en_GB' === $locale ) {
			$translations->add_entry(
				new Translation_Entry(
					array(
						'singular'     => 'F j, Y',
						'translations' => array( 'j F Y' ),
					)
				)
			);
			$translations->add_entry(
				new Translation_Entry(
					array(
						'singular'     => 'g:i a',
						'translations' => array( 'H:i' ),
					)
				)
			);
		}

		$l10n['default'] = &$translations;
	}

	/**
	 * @covers ::update_date_time_formats_on_locale_change
	 */
	public function test_switching_language_updates_date_time_formats() {
		$user_language_old = get_locale();

		$using_locales_default_date_format = ( __( 'F j, Y' ) === get_option( 'date_format' ) );
		$using_locales_default_time_format = ( __( 'g:i a' ) === get_option( 'time_format' ) );

		// Switch to British English and mock translations
		$locale = 'en_GB';
		switch_to_locale( $locale );
		$this->mock_translations_for_locale( $locale );

		$user_language_new = get_locale();
		$this->assertSame( $locale, $user_language_new, 'Locale should have changed to ' . $locale );

		$this->assertSame( 'j F Y', __( 'F j, Y' ) );
		$this->assertSame( 'H:i', __( 'g:i a' ) );

		// Simulate the date and time formats being updated
		if ( $user_language_old !== $user_language_new ) {
			if ( $using_locales_default_date_format ) {
				update_option( 'date_format', __( 'F j, Y' ) );
			}

			if ( $using_locales_default_time_format ) {
				update_option( 'time_format', __( 'g:i a' ) );
			}
		}

		$this->assertSame( 'j F Y', get_option( 'date_format' ), 'Date format was not updated to British English default' );
		$this->assertSame( 'H:i', get_option( 'time_format' ), 'Time format was not updated to British English default' );
	}
}
