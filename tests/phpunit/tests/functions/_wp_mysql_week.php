<?php

/**
 * @group functions.php
 * @group query
 * @covers ::_wp_mysql_week
 */
class Tests_Functions__wp_mysql_week extends WP_UnitTestCase {
	/**
	 * @dataProvider data__wp_mysql_week
	 */
	public function test__wp_mysql_week( $column, $start_of_week, $expected ) {
		update_option( 'start_of_week', $start_of_week );
		$this->assertSame( $expected, _wp_mysql_week( $column ) );
	}

	/**
	 * @return array
	 */
	public function data__wp_mysql_week() {
		$data = array();
		for ( $day_of_week = 0; $day_of_week <= 6; $day_of_week++ ) {
			$key = 'post_date_' . $day_of_week;
			if ( $day_of_week < 2 ) {
				$data[ $key ] = array( '`post_date`', $day_of_week, "WEEK( `post_date`, $day_of_week )" );
			} else {
				$data[ $key ] = array( '`post_date`', $day_of_week, "WEEK( DATE_SUB( `post_date`, INTERVAL $day_of_week DAY ), 0 )" );
			}
		}

		return $data;
	}
}
