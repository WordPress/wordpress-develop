<?php

/**
 * Tests for wp_half_baked_url_encode()
 *
 * @ticket 42957
 *
 * @group functions.php
 * @covers ::wp_half_baked_url_encode
 */
class Tests_Functions_WpHalfBakedUrlEncode extends WP_UnitTestCase {

	public function _data_wp_half_baked_url_encode() {
		return array(
			array( '', '' ),
			array( '.', '%2E' ),
			array( '..', '.%2E' ),
			array( '...', '..%2E' ),
			array( 'Ending period.', 'Ending%20period%2E' ),
			array( 'Middle.period', 'Middle.period' ),
			array( 'No period', 'No%20period' ),
			array( 'Two.periods.', 'Two.periods%2E' ),
		);
	}

	/**
	 * @dataProvider _data_wp_half_baked_url_encode
	 *
	 * @param $input_url
	 * @param $expected
	 */
	public function test_size_format( $input_url, $expected ) {
		$this->assertSame( $expected, wp_half_baked_url_encode( $input_url ) );
	}
}
