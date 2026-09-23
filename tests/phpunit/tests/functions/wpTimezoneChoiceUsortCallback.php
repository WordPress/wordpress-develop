<?php

/**
 * Tests for the _wp_timezone_choice_usort_callback() function.
 *
 * @group functions
 *
 * @covers ::_wp_timezone_choice_usort_callback
 */
class Tests_Functions_WpTimezoneChoiceUsortCallback extends WP_UnitTestCase {

	/**
	 * @ticket 59953
	 *
	 * @dataProvider data_wp_timezone_choice_usort_callback
	 */
	public function test_wp_timezone_choice_usort_callback( $unsorted, $sorted ) {
		usort( $unsorted, '_wp_timezone_choice_usort_callback' );

		$this->assertSame( $sorted, $unsorted );
	}

	public function data_wp_timezone_choice_usort_callback() {
		return array(
			'just GMT+'                         => array(
				'unsorted' => array(
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+a',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+b',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+c',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+e',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+d',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+e',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+d',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+c',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+b',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+a',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
			),

			'mixed UTC and GMT'                 => array(
				'unsorted' => array(
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+a',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'UTC',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+c',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'UTC',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+d',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+d',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+c',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'GMT+a',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'UTC',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'UTC',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
			),

			'just alpha city'                   => array(
				'unsorted' => array(
					array(
						'continent'   => 'Etc',
						'city'        => 'a',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'e',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'b',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'd',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'c',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => 'Etc',
						'city'        => 'a',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'b',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'c',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'd',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => 'e',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
			),

			'not Etc continents are not sorted' => array(
				'unsorted' => array(
					array(
						'continent'   => 'd',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'c',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'a',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'd',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'e',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => 'd',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'c',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'a',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'd',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'e',
						'city'        => '',
						't_continent' => '',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
			),

			'not Etc just t_continent'          => array(
				'unsorted' => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'd',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'b',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'e',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'c',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'b',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'c',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'd',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'e',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
			),

			'not Etc just t_city'               => array(
				'unsorted' => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'd',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'e',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'c',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'b',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'b',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'c',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'd',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'e',
						't_subcity'   => '',
					),
				),
			),

			'not Etc just t_subcity'            => array(
				'unsorted' => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'b',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'e',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'a',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'c',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'd',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'a',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'b',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'c',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'd',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => 'a',
						't_subcity'   => 'e',
					),
				),
			),

			'just continent with Etc which pulls 1 to bottom' => array(
				'unsorted' => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'b',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'c',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => '',
						't_continent' => '1',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'd',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
				'sorted'   => array(
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'a',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'b',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'c',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => '',
						'city'        => '',
						't_continent' => 'd',
						't_city'      => '',
						't_subcity'   => '',
					),
					array(
						'continent'   => 'Etc',
						'city'        => '',
						't_continent' => '1',
						't_city'      => '',
						't_subcity'   => '',
					),
				),
			),
		);
	}

	/**
	 * Test locale-aware sorting for translated timezone names.
	 *
	 * @ticket 11740
	 */
	public function test_wp_timezone_choice_usort_callback_locale_aware() {
		if ( ! class_exists( 'Collator' ) ) {
			$this->markTestSkipped( 'Intl extension not available.' );
		}

		add_filter(
			'locale',
			function () {
				return 'cs_CZ';
			}
		);

		$timezones = array(
			array(
				'continent'   => 'Europe',
				'city'        => 'Rome',
				't_continent' => 'Evropa',
				't_city'      => 'Řím',
				't_subcity'   => '',
			),
			array(
				'continent'   => 'Europe',
				'city'        => 'Amsterdam',
				't_continent' => 'Evropa',
				't_city'      => 'Amsterdam',
				't_subcity'   => '',
			),
			array(
				'continent'   => 'Europe',
				'city'        => 'Stockholm',
				't_continent' => 'Evropa',
				't_city'      => 'Stockholm',
				't_subcity'   => '',
			),
		);

		usort( $timezones, '_wp_timezone_choice_usort_callback' );

		$this->assertSame( 'Amsterdam', $timezones[0]['t_city'] );
		$this->assertSame( 'Řím', $timezones[1]['t_city'] );
		$this->assertSame( 'Stockholm', $timezones[2]['t_city'] );

		remove_all_filters( 'locale' );
	}

	/**
	 * Test fallback behavior when Intl extension is not available.
	 *
	 * @ticket 11740
	 */
	public function test_wp_timezone_choice_usort_callback_fallback() {
		$timezones = array(
			array(
				'continent'   => 'Europe',
				'city'        => 'Rome',
				't_continent' => 'Europe',
				't_city'      => 'Rome',
				't_subcity'   => '',
			),
			array(
				'continent'   => 'Europe',
				'city'        => 'Amsterdam',
				't_continent' => 'Europe',
				't_city'      => 'Amsterdam',
				't_subcity'   => '',
			),
		);

		usort( $timezones, '_wp_timezone_choice_usort_callback' );

		$this->assertSame( 'Amsterdam', $timezones[0]['t_city'] );
		$this->assertSame( 'Rome', $timezones[1]['t_city'] );
	}
}
