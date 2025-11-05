<?php

/**
 * Tests for the wp_timezone_choice function.
 *
 * @group Functions.php
 *
 * @covers ::wp_timezone_choice
 */
class Tests_Functions_wpTimezoneChoice extends WP_UnitTestCase {

	public function tear_down() {

		restore_current_locale();
	}
	/**
	 * Default values.
	 *
	 * @ticket 59941
	 *
	 * @dataProvider data_wp_timezone_choice
	 */
	public function test_wp_timezone_choice( $expected ) {
		$timezone_list = wp_timezone_choice( '' );
		$this->assertStringContainsString( $expected, $timezone_list );
	}

	/**
	 * Datasets for test_wp_timezone_choice.
	 *
	 * @return array
	 */
	public function data_wp_timezone_choice() {
		return array(
			'placeholder option' => array('<option selected="selected" value="">Select a city</option>'),
			'city in Americas' => array('<option value="America/Los_Angeles">Los Angeles</option>'),
			'deprecated timezone' => array('<option value="Pacific/Honolulu">Honolulu</option>'),
			'manual offset example' => array('<option value="UTC-8">UTC-8</option>'),
			'UTC option' => array('<option value="UTC">UTC</option>'),
			'continent example' => array('<option value="Africa/Johannesburg">Johannesburg</option>'),
			'city example' => array('<option value="Asia/Kuala_Lumpur">Kuala Lumpur</option>'),
			'city with sub-city' => array('<option value="America/Argentina/Buenos_Aires">Argentina - Buenos Aires</option>'),
			'translated city name appears' => array('<option value="Pacific/Port_Moresby">Port Moresby</option>'),
		);
	}

	/**
	 * Zones are selected from the list.
	 *
	 * @ticket 59941
	 *
	 * @dataProvider data_wp_timezone_choice_selected
	 */
	public function test_wp_timezone_choice_selected( $selected_zone, $expected ) {
		$actual = wp_timezone_choice( $selected_zone );
		$this->assertStringContainsString( $expected, $actual );
	}

	/**
	 * Datasets for test_wp_timezone_choice_selected.
	 *
	 * @return array
	 */
	public function data_wp_timezone_choice_selected() {
		return array(
			'city from the list' => array(
				'America/Los_Angeles',
				'<option selected="selected" value="America/Los_Angeles">Los Angeles</option>',
			),
			'deprecated but valid timezone string' => array(
				'Pacific/Auckland',
				'<option selected="selected" value="Pacific/Auckland">Auckland</option>',
			),
			'UTC' => array(
				'UTC',
				'<option selected="selected" value="UTC">UTC</option>',
			),
			'manual UTC offset' => array(
				'UTC+10',
				'<option selected="selected" value="UTC+10">UTC+10</option>',
			),
		);
	}

	/**
	 * Test passing the locale in.
	 *
	 * @ticket 59941
	 */
	/**
	 * Test passing the locale in.
	 *
	 * @ticket 59941
	 *
	 * @dataProvider data_wp_timezone_choice_es
	 */
	public function test_wp_timezone_choice_es( $expected ) {
		$timezone_list = wp_timezone_choice( '', 'es_ES' );
		$this->assertStringContainsString( $expected, $timezone_list );
	}

	/**
	 * Datasets for test_wp_timezone_choice_es.
	 *
	 * @return array
	 */
	public function data_wp_timezone_choice_es() {
		return array(
			'placeholder remains in English (no translation override passed)' => array('<option selected="selected" value="">Select a city</option>'),
			'spanish city translation' => array('<option value="Pacific/Port_Moresby">Puerto Moresby</option>'),
			'spanish optgroup Arctic' => array('<optgroup label="Ártico">'),
			'spanish optgroup Manual Offsets untranslated' => array('<optgroup label="Manual Offsets">'),
		);
	}

	/**
	 * Set the locale globally.
	 *
	 * @ticket 59941
	 *
	 * @dataProvider data_wp_timezone_choice_es_set
	 */
	public function test_wp_timezone_choice_es_set( $expected ) {
		switch_to_locale( 'es_ES' );
		$timezone_list = wp_timezone_choice( '' );
		$this->assertStringContainsString( $expected, $timezone_list );
	}

	/**
	 * Datasets for test_wp_timezone_choice_es_set.
	 *
	 * @return array
	 */
	public function data_wp_timezone_choice_es_set() {
		return array(
			'placeholder in Spanish' => array('<option selected="selected" value="">Elige una ciudad</option>'),
			'spanish city translation' => array('<option value="Pacific/Port_Moresby">Puerto Moresby</option>'),
			'spanish optgroup Arctic' => array('<optgroup label="Ártico">'),
			'spanish optgroup Manual Offsets' => array('<optgroup label="Compensaciones manuales">'),
		);
	}
}
