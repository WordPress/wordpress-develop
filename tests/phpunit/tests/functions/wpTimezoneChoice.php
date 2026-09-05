<?php

/**
 * Tests for the wp_timezone_choice() function.
 *
 * @group functions
 *
 * @covers ::wp_timezone_choice
 */
class Tests_Functions_WpTimezoneChoice extends WP_UnitTestCase {

	/**
	 * Restores the current locale after each test runs.
	 */
	public function tear_down(): void {
		restore_current_locale();
		parent::tear_down();
	}

	/**
	 * Tests default values.
	 *
	 * @ticket 59941
	 * @dataProvider data_wp_timezone_choice
	 *
	 * @param string $expected Expected string HTML fragment.
	 */
	public function test_wp_timezone_choice( string $expected ): void {
		$timezone_list = wp_timezone_choice( '' );
		$this->assertStringContainsString( $expected, $timezone_list );
	}

	/**
	 * Data provider for test_wp_timezone_choice().
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public function data_wp_timezone_choice(): array {
		return array(
			'placeholder option'           => array( '<option selected="selected" value="">Select a city</option>' ),
			'city in Americas'             => array( '<option value="America/Los_Angeles" dir="auto">Los Angeles</option>' ),
			'deprecated timezone'          => array( '<option value="Pacific/Honolulu" dir="auto">Honolulu</option>' ),
			'manual offset example'        => array( '<option value="UTC-8" dir="auto">UTC-8</option>' ),
			'UTC option'                   => array( '<option value="UTC" dir="auto">UTC</option>' ),
			'continent example'            => array( '<option value="Africa/Johannesburg" dir="auto">Johannesburg</option>' ),
			'city example'                 => array( '<option value="Asia/Kuala_Lumpur" dir="auto">Kuala Lumpur</option>' ),
			'city with sub-city'           => array( '<option value="America/Argentina/Buenos_Aires" dir="auto">Argentina - Buenos Aires</option>' ),
			'translated city name appears' => array( '<option value="Pacific/Port_Moresby" dir="auto">Port Moresby</option>' ),
		);
	}

	/**
	 * Tests zones are selected from the list.
	 *
	 * @ticket 59941
	 * @dataProvider data_wp_timezone_choice_selected
	 *
	 * @param string $selected_zone The timezone to select.
	 * @param string $expected      Expected string HTML fragment.
	 */
	public function test_wp_timezone_choice_selected( string $selected_zone, string $expected ): void {
		$actual = wp_timezone_choice( $selected_zone );
		$this->assertStringContainsString( $expected, $actual );
	}

	/**
	 * Data provider for test_wp_timezone_choice_selected().
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public function data_wp_timezone_choice_selected(): array {
		return array(
			'city from the list'                   => array(
				'America/Los_Angeles',
				'<option selected="selected" value="America/Los_Angeles" dir="auto">Los Angeles</option>',
			),
			'deprecated but valid timezone string' => array(
				'Pacific/Auckland',
				'<option selected="selected" value="Pacific/Auckland" dir="auto">Auckland</option>',
			),
			'UTC'                                  => array(
				'UTC',
				'<option selected="selected" value="UTC" dir="auto">UTC</option>',
			),
			'manual UTC offset'                    => array(
				'UTC+10',
				'<option selected="selected" value="UTC+10" dir="auto">UTC+10</option>',
			),
		);
	}

	/**
	 * Tests passing in the locale.
	 *
	 * @ticket 59941
	 * @dataProvider data_wp_timezone_choice_es
	 *
	 * @param string $expected Expected string HTML fragment.
	 */
	public function test_wp_timezone_choice_es( string $expected ): void {
		$timezone_list = wp_timezone_choice( '', 'es_ES' );
		$this->assertStringContainsString( $expected, $timezone_list );
	}

	/**
	 * Data provider for test_wp_timezone_choice_es().
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public function data_wp_timezone_choice_es(): array {
		return array(
			'placeholder remains in English (no translation override passed)' => array( '<option selected="selected" value="">Select a city</option>' ),
			'spanish city translation'                     => array( '<option value="Pacific/Port_Moresby" dir="auto">Puerto Moresby</option>' ),
			'spanish optgroup Arctic'                      => array( '<optgroup label="Ártico" dir="auto">' ),
			'spanish optgroup Manual Offsets untranslated' => array( '<optgroup label="Manual Offsets" dir="auto">' ),
		);
	}

	/**
	 * Tests setting the locale globally.
	 *
	 * @ticket 59941
	 * @dataProvider data_wp_timezone_choice_es_set
	 *
	 * @param string $expected Expected string HTML fragment.
	 */
	public function test_wp_timezone_choice_es_set( string $expected ): void {
		switch_to_locale( 'es_ES' );
		$timezone_list = wp_timezone_choice( '' );
		$this->assertStringContainsString( $expected, $timezone_list );
	}

	/**
	 * Data provider for test_wp_timezone_choice_es_set().
	 *
	 * @return array<string, array{ 0: string }>
	 */
	public static function data_wp_timezone_choice_es_set(): array {
		return array(
			'placeholder in Spanish'          => array( '<option selected="selected" value="">Elige una ciudad</option>' ),
			'spanish city translation'        => array( '<option value="Pacific/Port_Moresby" dir="auto">Puerto Moresby</option>' ),
			'spanish optgroup Arctic'         => array( '<optgroup label="Ártico" dir="auto">' ),
			'spanish optgroup Manual Offsets' => array( '<optgroup label="Compensaciones manuales" dir="auto">' ),
		);
	}
}
