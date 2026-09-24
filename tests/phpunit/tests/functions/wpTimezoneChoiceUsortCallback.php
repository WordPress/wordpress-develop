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
}
