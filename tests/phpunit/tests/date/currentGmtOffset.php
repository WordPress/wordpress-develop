<?php

/**
 * @group date
 * @group datetime
 * @covers ::current_gmt_offset()
 */
class Tests_Date_CurrentGmtOffset extends WP_UnitTestCase {

	/**
	 * Cleans up.
	 */
	public function tear_down() {
		// Reset changed options to their default value.
		update_option( 'gmt_offset', 0 );

		parent::tear_down();
	}

	/**
	 * @ticket 58986
	 */
	public function test_current_gmt_offset_empty_is_default() {
		delete_option( 'gmt_offset' );

		$this->assertIsFloat( current_gmt_offset(), 'Missing current GMT offset is default of 0' );
	}

	/**
	 * @ticket 58986
	 */
	public function test_current_gmt_offset_invalid_boolean_true() {
		update_option( 'gmt_offset', true );

		$this->assertEquals( 0, current_gmt_offset(), 'Invalid current GMT offset of boolean true is 0' );
	}

	/**
	 * @ticket 58986
	 */
	public function test_current_gmt_offset_invalid_string() {
		update_option( 'gmt_offset', 'NinePointFive' );

		$this->assertEquals( 0, current_gmt_offset(), 'Invalid current GMT offset of non-numeric text is 0' );
	}

	/**
	 * @ticket 58986
	 */
	public function test_current_gmt_offset_is_float() {
		update_option( 'gmt_offset', 9.5 );

		$this->assertIsFloat( current_gmt_offset(), 'Current GMT offset is a float' );
	}
}
