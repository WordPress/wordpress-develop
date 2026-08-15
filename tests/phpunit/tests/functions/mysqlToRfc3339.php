<?php

/**
 * @group functions
 *
 * @covers ::mysql_to_rfc3339
 */
class Tests_Functions_MysqlToRfc3339 extends WP_UnitTestCase {
	/**
	 * @ticket 36054
	 * @dataProvider data_mysql_to_rfc3339
	 */
	public function test_mysql_to_rfc3339( $expected, $actual ) {
		$date_return = mysql_to_rfc3339( $actual );

		$this->assertIsString( $date_return, 'The date return must be a string' );
		$this->assertNotEmpty( $date_return, 'The date return could not be an empty string' );
		$this->assertSame( $expected, $date_return, 'The date does not match' );
		$this->assertEquals( new DateTime( $expected ), new DateTime( $date_return ), 'The date is not the same after the call method' );
	}

	public function data_mysql_to_rfc3339() {
		return array(
			array( '2016-03-15T18:54:46', '15-03-2016 18:54:46' ),
			array( '2016-03-02T19:13:25', '2016-03-02 19:13:25' ),
			array( '2016-03-02T19:13:00', '2016-03-02 19:13' ),
			array( '2016-03-02T19:13:00', '16-03-02 19:13' ),
			array( '2016-03-02T19:13:00', '16-03-02 19:13' ),
		);
	}
}
