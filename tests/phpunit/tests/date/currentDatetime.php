<?php

/**
 * @group date
 * @group datetime
 * @covers ::current_datetime
 */
class Tests_Date_CurrentDatetime extends WP_UnitTestCase {

	/**
	 * @ticket 53484
	 */
	public function test_current_datetime_return_type() {
		$this->assertInstanceOf( 'DateTimeImmutable', current_datetime() );
	}
}
