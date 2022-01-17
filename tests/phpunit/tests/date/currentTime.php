<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_CurrentTime extends WP_UnitTestCase {

	public function test_should_work_with_changed_timezone() {

		$format          = 'Y-m-d H:i:s';
		$timezone_string = 'America/Regina';
		update_option( 'timezone_string', $timezone_string );
		$datetime = new DateTime( 'now', new DateTimeZone( $timezone_string ) );

		date_default_timezone_set( $timezone_string );

		$current_time_custom_timezone_gmt = current_time( $format, true );
		$current_time_custom_timezone     = current_time( $format );

		date_default_timezone_set( 'UTC' );

		$current_time_gmt = current_time( $format, true );
		$current_time     = current_time( $format );

		$this->assertEquals( strtotime( gmdate( $format ) ), strtotime( $current_time_custom_timezone_gmt ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( $datetime->format( $format ) ), strtotime( $current_time_custom_timezone ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( gmdate( $format ) ), strtotime( $current_time_gmt ), 'The dates should be equal', 2 );
		$this->assertEquals( strtotime( $datetime->format( $format ) ), strtotime( $current_time ), 'The dates should be equal', 2 );
	}
}
