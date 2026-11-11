<?php

/**
 * Tests for the is_lighttpd_before_150 function.
 *
 * @group functions
 *
 * @covers ::is_lighttpd_before_150
 */
class Tests_Functions_isLighttpdBefore150 extends WP_UnitTestCase{

	/**
	 * @ticket 60056
	 * @dataProvider data_is_lighttpd_before_150
	 *
	 * @param string|null $server_software Server value, or null to unset it.
	 * @param bool        $expected        Expected result.
	 */
	public function test_is_lighttpd_before_150( $server_software, $expected ) {
		$had_server_software      = array_key_exists( 'SERVER_SOFTWARE', $_SERVER );
		$original_server_software = $had_server_software ? $_SERVER['SERVER_SOFTWARE'] : null;

		try {
			if ( null === $server_software ) {
				unset( $_SERVER['SERVER_SOFTWARE'] );
			} else {
				$_SERVER['SERVER_SOFTWARE'] = $server_software;
			}

			$this->assertSame( $expected, is_lighttpd_before_150() );
		} finally {
			if ( $had_server_software ) {
				$_SERVER['SERVER_SOFTWARE'] = $original_server_software;
			} else {
				unset( $_SERVER['SERVER_SOFTWARE'] );
			}
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_is_lighttpd_before_150() {
		return array(
			'below 1.5.0'     => array( 'lighttpd/1.4.0', true ),
			'exactly 1.5.0'   => array( 'lighttpd/1.5.0', false ),
			'above 1.5.0'     => array( 'lighttpd/1.6.0', false ),
			'another server' => array( 'Apache/2.4.0', false ),
			'missing value'  => array( null, false ),
		);
	}
}
