<?php

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * @group admin
 * @ticket 66709
 * @covers ::_wp_generate_fallback_auth_keys
 * @covers ::_wp_fetch_installation_auth_keys
 */
class Tests_Admin_Includes_Upgrade_GenerateFallbackAuthKeys_Test extends WP_UnitTestCase {

	const KEY_NAMES = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	/**
	 * @dataProvider data_valid_responses
	 *
	 * @param string $body     Response body.
	 * @param array  $expected Expected keys indexed by constant name.
	 */
	public function test_accepts_complete_response( $body, $expected ) {
		$this->mock_remote_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => $body,
			)
		);

		$keys = _wp_generate_fallback_auth_keys( self::KEY_NAMES, false );

		$this->assertSameSetsWithIndex( $expected, $keys );
	}

	/**
	 * @return array[]
	 */
	public static function data_valid_responses() {
		$keys  = self::get_remote_keys();
		$lines = self::get_response_lines( $keys );

		return array(
			'normal response'                => array( implode( "\n", $lines ) . "\n", $keys ),
			'explicit name mapping and CRLF' => array( implode( "\r\n", array_reverse( $lines ) ), $keys ),
		);
	}

	/**
	 * @dataProvider data_invalid_responses
	 *
	 * @param array|string $response Mock HTTP response, or the transport error marker.
	 */
	public function test_uses_local_keys_when_the_response_is_invalid( $response ) {
		if ( 'transport error' === $response ) {
			$response = new WP_Error( 'http_request_failed', 'Test transport failure.' );
		}

		$this->mock_remote_response( $response );
		$this->assertSame( $this->mock_local_keys(), _wp_generate_fallback_auth_keys( self::KEY_NAMES, false ) );
	}

	/**
	 * @return array[]
	 */
	public static function data_invalid_responses() {
		$keys  = self::get_remote_keys();
		$lines = self::get_response_lines( $keys );
		$body  = implode( "\n", $lines );

		$responses = array(
			'transport error'       => array( 'transport error' ),
			'missing HTTP status'   => array(
				array(
					'response' => array(),
					'body'     => $body,
				),
			),
			'HTTP error'            => array(
				array(
					'response' => array( 'code' => 503 ),
					'body'     => $body,
				),
			),
			'partial HTTP response' => array(
				array(
					'response' => array( 'code' => 206 ),
					'body'     => $body,
				),
			),
			'missing body'          => array( array( 'response' => array( 'code' => 200 ) ) ),
			'non-string body'       => array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => array(),
				),
			),
		);

		$invalid_bodies = array(
			'empty body'        => '',
			'HTML error page'   => '<html>Service unavailable</html>',
			'missing record'    => implode( "\n", array_slice( $lines, 1 ) ),
			'extra record'      => $body . "\n" . $lines[0],
			'duplicate name'    => implode( "\n", array_merge( array( $lines[1] ), array_slice( $lines, 1 ) ) ),
			'unknown name'      => str_replace( "'AUTH_KEY'", "'UNKNOWN_KEY'", $body ),
			'invalid record'    => str_replace( 'define', 'setting', $body ),
			'at the size limit' => str_pad( $body, 4096, "\n" ),
			'oversized body'    => $body . str_repeat( "\n", 4096 ),
		);

		foreach ( $invalid_bodies as $case => $invalid_body ) {
			$responses[ $case ] = array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $invalid_body,
				),
			);
		}

		$invalid_values = array(
			'short value'     => str_repeat( 'z', 63 ),
			'long value'      => str_repeat( 'z', 65 ),
			'empty value'     => '',
			'duplicate value' => $keys['SECURE_AUTH_KEY'],
			'quote'           => str_repeat( 'z', 63 ) . "'",
			'backslash'       => str_repeat( 'z', 63 ) . '\\',
			'control byte'    => str_repeat( 'z', 63 ) . "\x00",
			'non-ASCII byte'  => str_repeat( 'z', 63 ) . "\xFF",
		);

		foreach ( $invalid_values as $case => $invalid_value ) {
			$invalid_keys             = $keys;
			$invalid_keys['AUTH_KEY'] = $invalid_value;
			$responses[ $case ]       = array(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => implode( "\n", self::get_response_lines( $invalid_keys ) ),
				),
			);
		}

		return $responses;
	}

	public function test_skips_the_request_when_the_api_is_disabled() {
		add_filter(
			'pre_http_request',
			function () {
				$this->fail( 'The fallback must skip the HTTP request when the API is disabled.' );
			}
		);

		$this->assertSame( $this->mock_local_keys(), _wp_generate_fallback_auth_keys( self::KEY_NAMES, true ) );
	}

	private static function get_remote_keys() {
		$keys = array();
		foreach ( self::KEY_NAMES as $index => $name ) {
			$keys[ $name ] = ' ' . str_repeat( chr( 65 + $index ), 61 ) . '! ';
		}
		return $keys;
	}

	private static function get_response_lines( $keys ) {
		$lines = array();
		foreach ( $keys as $name => $value ) {
			$lines[] = "define( '$name', '$value' );";
		}
		return $lines;
	}

	private function mock_remote_response( $response ) {
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $response ) {
				$this->assertSame( 'https://api.wordpress.org/secret-key/1.1/salt/', $url );
				return $response;
			},
			10,
			3
		);
	}

	private function mock_local_keys() {
		$keys = array();
		foreach ( self::KEY_NAMES as $index => $name ) {
			$keys[ $name ] = str_pad( 'local-' . $index, 64, 'x' );
		}
		$passwords = array_values( $keys );
		add_filter(
			'random_password',
			static function () use ( &$passwords ) {
				return array_shift( $passwords );
			}
		);
		return $keys;
	}
}
