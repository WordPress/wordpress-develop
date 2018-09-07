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
		$this->assertEquals( gmdate( $format ), current_time( $format, true ) );
		$this->assertEquals( $datetime->format( $format ), current_time( $format ) );

		date_default_timezone_set( 'UTC' );
		$this->assertEquals( gmdate( $format ), current_time( $format, true ) );
		$this->assertEquals( $datetime->format( $format ), current_time( $format ) );
	}
}
