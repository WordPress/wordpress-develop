<?php
/**
 * Tests for wp_update_date_time_formats_on_locale_change().
 *
 * @group admin
 * @group l10n
 * @group i18n
 *
 * @covers ::wp_update_date_time_formats_on_locale_change
 */
class Tests_Admin_Includes_Options_WpUpdateDateTimeFormatsOnLocaleChange extends WP_UnitTestCase {

	/**
	 * Loads the tested file.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		require_once ABSPATH . 'wp-admin/includes/options.php';
	}

	/**
	 * Restores the default translations.
	 */
	public function tear_down() {
		set_current_screen( 'front' );
		load_default_textdomain();

		parent::tear_down();
	}

	/**
	 * @ticket 36259
	 * @dataProvider data_should_update_default_formats
	 *
	 * @param string $old_locale The previous site locale.
	 * @param string $new_locale The new site locale.
	 * @param string $old_date   The date format before the change.
	 * @param string $old_time   The time format before the change.
	 * @param string $new_date   The expected date format after the change.
	 * @param string $new_time   The expected time format after the change.
	 */
	public function test_should_update_default_formats( $old_locale, $new_locale, $old_date, $old_time, $new_date, $new_time ) {
		update_option( 'date_format', $old_date );
		update_option( 'time_format', $old_time );

		wp_update_date_time_formats_on_locale_change( $old_locale, $new_locale );

		$this->assertSame( $new_date, get_option( 'date_format' ), 'The date format is not correct.' );
		$this->assertSame( $new_time, get_option( 'time_format' ), 'The time format is not correct.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_should_update_default_formats() {
		return array(
			'English to German, default formats'  => array( 'en_US', 'de_DE', 'F j, Y', 'g:i a', 'j. F Y', 'G:i' ),
			'German to Spanish, default formats'  => array( 'de_DE', 'es_ES', 'j. F Y', 'G:i', 'j \d\e F \d\e Y', 'H:i' ),
			'Spanish to English, default formats' => array( 'es_ES', 'en_US', 'j \d\e F \d\e Y', 'H:i', 'F j, Y', 'g:i a' ),
			'English to German, custom date'      => array( 'en_US', 'de_DE', 'Y-m-d', 'g:i a', 'Y-m-d', 'G:i' ),
			'English to German, custom time'      => array( 'en_US', 'de_DE', 'F j, Y', 'H:i:s', 'j. F Y', 'H:i:s' ),
			'English to German, custom formats'   => array( 'en_US', 'de_DE', 'd M Y', 'g:i A', 'd M Y', 'g:i A' ),
			'German to English, English formats'  => array( 'de_DE', 'en_US', 'F j, Y', 'g:i a', 'F j, Y', 'g:i a' ),
			'Same locale, other locale defaults'  => array( 'en_US', 'en_US', 'j. F Y', 'G:i', 'j. F Y', 'G:i' ),
		);
	}

	/**
	 * @ticket 36259
	 */
	public function test_should_restore_translations_of_current_locale() {
		wp_update_date_time_formats_on_locale_change( 'en_US', 'de_DE' );

		$this->assertSame( 'F j, Y', __( 'F j, Y' ) );
	}

	/**
	 * @ticket 36259
	 */
	public function test_should_use_site_locales_when_user_locale_differs() {
		$user_id = self::factory()->user->create(
			array(
				'role'   => 'administrator',
				'locale' => 'de_DE',
			)
		);
		wp_set_current_user( $user_id );
		set_current_screen( 'options-general' );

		update_option( 'date_format', 'j \d\e F \d\e Y' );
		update_option( 'time_format', 'H:i' );

		wp_update_date_time_formats_on_locale_change( 'es_ES', 'en_US' );

		$this->assertSame( 'F j, Y', get_option( 'date_format' ), 'The date format is not correct.' );
		$this->assertSame( 'g:i a', get_option( 'time_format' ), 'The time format is not correct.' );
		$this->assertSame( 'j. F Y', __( 'F j, Y' ), 'The translations of the user locale were not restored.' );
	}
}
