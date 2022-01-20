<?php

/**
 * @group functions.php
 *
 * @covers ::current_datetime
 */
class Tests_Functions_current_datetime extends WP_UnitTestCase {

	/**
	 * test the current_datetime function return correct class type
	 * @return void
	 *
	 */
	public function test_current_datetime_return_type() {

		$this->assertInstanceOf( 'DateTimeImmutable', current_datetime() );
	}
}
