<?php

/**
 * Tests the wp_validate_boolean function.
 *
 * @covers ::wp_validate_boolean
 * @group functions.php
 */
class Tests_Functions_wpValidateBoolean extends WP_UnitTestCase {
	/**
	 * Provides test scenarios for all possible scenarios in wp_validate_boolean().
	 *
	 * @return array
	 */
	function data_provider() {
			$std = new \stdClass();

			return array(
				array( null, false ),
				array( true, true ),
				array( false, false ),
				array( 'true', true ),
				array( 'false', false ),
				array( 'FalSE', false ), // @ticket 30238
				array( 'FALSE', false ), // @ticket 30238
				array( 'TRUE', true ),
				array( ' FALSE ', true ),
				array( 'yes', true ),
				array( 'no', true ),
				array( 'string', true ),
				array( '', false ),
				array( array(), false ),
				array( 1, true ),
				array( 0, false ),
				array( -1, true ),
				array( 99, true ),
				array( 0.1, true ),
				array( 0.0, false ),
				array( '1', true ),
				array( '0', false ),
				array( $std, true ),
			);
	}

	/**
	 * Test wp_validate_boolean().
	 *
	 * @dataProvider data_provider
	 *
	 * @param mixed $test_value.
	 * @param bool $expected.
	 *
	 * @ticket 30238
	 * @ticket 39868
	 */
	public function test_wp_validate_boolean( $test_value, $expected ) {
		$this->assertSame( wp_validate_boolean( $test_value ), $expected );
	}
}
