<?php

/**
 * @group date
 * @group datetime
 */
class Tests_Date_WP_Date extends WP_UnitTestCase {

	/**
	 * @ticket 28636
	 */
	public function test_should_return_false_on_invalid_timestamp() {
		$this->assertFalse( wp_date( DATE_RFC3339, 'invalid' ) );
	}
}
