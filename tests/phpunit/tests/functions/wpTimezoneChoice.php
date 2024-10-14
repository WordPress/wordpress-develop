<?php

/**
 * Tests for the wp_timezone_choice function.
 *
 * @group Functions.php
 *
 * @covers ::wp_timezone_choice
 */
class Tests_Functions_wpTimezoneChoice extends WP_UnitTestCase {

	/**
	 * Default values
	 *
	 * @ticket 59941
	 */
	public function test_wp_timezone_choice() {
		// Test selecting a timezone from the list.
		$timezone_list            = wp_timezone_choice( '' );
		$selected_timezone_option = '<option selected="selected" value="">Select a city</option>';
		$this->assertStringContainsString( $selected_timezone_option, $timezone_list );

		$selected_timezone_option = '<option value="America/Los_Angeles">Los Angeles</option>';
		$this->assertStringContainsString( $selected_timezone_option, $timezone_list );

		// Test selecting a deprecated timezone.
		$deprecated_timezone_option = '<option value="Pacific/Honolulu">Honolulu</option>';
		$this->assertStringContainsString( $deprecated_timezone_option, $timezone_list );

		// Test selecting a manual offset.
		$manual_offset_option = '<option value="UTC-8">UTC-8</option>';
		$this->assertStringContainsString( $manual_offset_option, $timezone_list );

		// Test selecting UTC.
		$utc_option = '<option value="UTC">UTC</option>';
		$this->assertStringContainsString( $utc_option, $timezone_list );

		// Test selecting a timezone from a continent.
		$timezone_from_continent_option = '<option value="Africa/Johannesburg">Johannesburg</option>';
		$this->assertStringContainsString( $timezone_from_continent_option, $timezone_list );

		// Test selecting a timezone from a specific city.
		$timezone_from_city_option = '<option value="Asia/Kuala_Lumpur">Kuala Lumpur</option>';
		$this->assertStringContainsString( $timezone_from_city_option, $timezone_list );

		// Test selecting a timezone from a specific city and sub-city.
		$timezone_from_city_and_subcity_option = '<option value="America/Argentina/Buenos_Aires">Argentina - Buenos Aires</option>';
		$this->assertStringContainsString( $timezone_from_city_and_subcity_option, $timezone_list );

		// Test an tranalated city.
		$timezone_from_city_es = '<option value="Pacific/Port_Moresby">Port Moresby</option>';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );
	}


	/**
	 * zones are selected from the list.
	 *
	 * @ticket 59941
	 */
	public function test_wp_timezone_choice_selected() {
		// Test selecting a city from the list.
		$selected_zone = 'America/Los_Angeles';
		$expected      = '<option selected="selected" value="America/Los_Angeles">Los Angeles</option>';
		$actual        = wp_timezone_choice( $selected_zone );
		$this->assertStringContainsString( $expected, $actual );

		// Test selecting a deprecated, but valid, timezone string.
		$selected_zone = 'Pacific/Auckland';
		$expected      = '<option selected="selected" value="Pacific/Auckland">Auckland</option>';
		$actual        = wp_timezone_choice( $selected_zone );
		$this->assertStringContainsString( $expected, $actual );

		// Test selecting UTC.
		$selected_zone = 'UTC';
		$expected      = '<option selected="selected" value="UTC">UTC</option>';
		$actual        = wp_timezone_choice( $selected_zone );
		$this->assertStringContainsString( $expected, $actual );

		// Test selecting a manual UTC offset.
		$selected_zone = 'UTC+10';
		$expected      = '<option selected="selected" value="UTC+10">UTC+10</option>';
		$actual        = wp_timezone_choice( $selected_zone );
		$this->assertStringContainsString( $expected, $actual );
	}


	/**
	 * Test passing the locale in
	 *
	 * @ticket 59941
	 */
	public function test_wp_timezone_choice_es() {
		// Test selecting a timezone from the list.
		$timezone_list            = wp_timezone_choice( '', 'es_ES' );
		$selected_timezone_option = '<option selected="selected" value="">Select a city</option>';
		$this->assertStringContainsString( $selected_timezone_option, $timezone_list );

		$timezone_from_city_es = '<option value="Pacific/Port_Moresby">Puerto Moresby</option>';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );

		$timezone_from_city_es = '<optgroup label="Ártico">';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );

		$timezone_from_city_es = '<optgroup label="Manual Offsets">';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );
	}

	/**
	 * Sett the locale globally
	 *
	 * @ticket 59941
	 */
	public function test_wp_timezone_choice_es_set() {

		switch_to_locale( 'es_ES' );
		// Test selecting a timezone from the list.
		$timezone_list            = wp_timezone_choice( '' );
		$selected_timezone_option = '<option selected="selected" value="">Elige una ciudad</option>';
		$this->assertStringContainsString( $selected_timezone_option, $timezone_list );

		$timezone_from_city_es = '<option value="Pacific/Port_Moresby">Puerto Moresby</option>';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );

		$timezone_from_city_es = '<optgroup label="Ártico">';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );

		$timezone_from_city_es = '<optgroup label="Compensaciones manuales">';
		$this->assertStringContainsString( $timezone_from_city_es, $timezone_list );

		restore_current_locale();
	}
}
