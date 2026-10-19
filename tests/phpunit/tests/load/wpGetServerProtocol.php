<?php

/**
 * Tests for wp_get_server_protocol().
 *
 * @group load
 *
 * @covers ::wp_get_server_protocol
 */
class Tests_Load_WpGetServerProtocol extends WP_UnitTestCase {

	/**
	 * Original SERVER_PROTOCOL value.
	 *
	 * @var string|null
	 */
	private $original_server_protocol;

	public function set_up() {
		parent::set_up();

		$this->original_server_protocol = isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : null;
	}

	public function tear_down() {
		if ( null === $this->original_server_protocol ) {
			unset( $_SERVER['SERVER_PROTOCOL'] );
		} else {
			$_SERVER['SERVER_PROTOCOL'] = $this->original_server_protocol;
		}

		parent::tear_down();
	}

	/**
	 * Tests recognized HTTP protocol versions and the fallback for unknown values.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_wp_get_server_protocol
	 *
	 * @param string|null $server_protocol Value for $_SERVER['SERVER_PROTOCOL'], or null to unset.
	 * @param string      $expected        Expected return value.
	 */
	public function test_wp_get_server_protocol( $server_protocol, $expected ) {
		if ( null === $server_protocol ) {
			unset( $_SERVER['SERVER_PROTOCOL'] );
		} else {
			$_SERVER['SERVER_PROTOCOL'] = $server_protocol;
		}

		$this->assertSame( $expected, wp_get_server_protocol() );
	}

	/**
	 * Data provider for test_wp_get_server_protocol().
	 *
	 * @return array[]
	 */
	public static function data_wp_get_server_protocol() {
		return array(
			'HTTP/1.1'       => array( 'HTTP/1.1', 'HTTP/1.1' ),
			'HTTP/2'         => array( 'HTTP/2', 'HTTP/2' ),
			'HTTP/2.0'       => array( 'HTTP/2.0', 'HTTP/2.0' ),
			'HTTP/3'         => array( 'HTTP/3', 'HTTP/3' ),
			'HTTP/1.0'       => array( 'HTTP/1.0', 'HTTP/1.0' ),
			'unknown value'  => array( 'HTTP/0.9', 'HTTP/1.0' ),
			'empty string'   => array( '', 'HTTP/1.0' ),
			'missing header' => array( null, 'HTTP/1.0' ),
		);
	}
}
