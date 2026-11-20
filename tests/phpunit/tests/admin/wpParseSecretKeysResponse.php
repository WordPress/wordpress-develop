<?php

/**
 * Tests for `_wp_parse_secret_keys_response()`.
 *
 * @group admin
 * @group upgrade
 *
 * @covers ::_wp_parse_secret_keys_response
 */
class Tests_Admin_WpParseSecretKeysResponse extends WP_UnitTestCase {

	/**
	 * Loads the upgrade functions required for the tests.
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	}

	/**
	 * Builds a valid 8-line secret-key API response using the given 64-character value.
	 *
	 * @param string $value Secret key value to place in each line.
	 * @param string $eol   Line ending to use.
	 * @return string
	 */
	private static function build_response( string $value, string $eol = "\n" ): string {
		$constants = array(
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		$lines = array();
		foreach ( $constants as $constant ) {
			$lines[] = sprintf( "define('%s',%s'%s');", $constant, str_repeat( ' ', max( 1, 18 - strlen( $constant ) ) ), $value );
		}

		return implode( $eol, $lines );
	}

	/**
	 * Tests that a valid 8-line secret-key API response returns all eight keys.
	 *
	 * @ticket 66709
	 */
	public function test_returns_eight_keys_for_valid_response() {
		$key      = '@)e1(:KEGx0^+F4R5adMOXJZ@i<kkLs$fC;Dg%RJ4C|*w5ZhH{+ZzR6yWAKx~:]c';
		$response = self::build_response( $key ) . "\n";

		$parsed = _wp_parse_secret_keys_response( $response );

		$this->assertIsArray( $parsed, 'Valid response should parse into an array.' );
		$this->assertCount( 8, $parsed, 'Valid response should return eight keys.' );
		$this->assertSame( array_fill( 0, 8, $key ), $parsed, 'Each parsed key should match the 64-character API value.' );
	}

	/**
	 * Tests that a valid CRLF-delimited response is accepted.
	 *
	 * @ticket 66709
	 */
	public function test_returns_eight_keys_for_crlf_response() {
		$key      = 'w&7hJe>:t:p_>zHfVh-ZI.^|)K[)<C5z]+svY|n^.AT>7!cxw=.-N(LQcrEq@xGR';
		$response = self::build_response( $key, "\r\n" ) . "\r\n";

		$parsed = _wp_parse_secret_keys_response( $response );

		$this->assertSame( array_fill( 0, 8, $key ), $parsed, 'CRLF line endings should be accepted.' );
	}

	/**
	 * Tests that malformed or unsafe secret-key API responses return null.
	 *
	 * @ticket 66709
	 *
	 * @dataProvider data_invalid_secret_keys_responses
	 *
	 * @param mixed $body Response body to validate.
	 */
	public function test_returns_null_for_invalid_response( $body ) {
		$this->assertNull( _wp_parse_secret_keys_response( $body ), 'Invalid or unsafe API responses must be rejected.' );
	}

	/**
	 * Data provider for test_returns_null_for_invalid_response().
	 *
	 * @return array[]
	 */
	public static function data_invalid_secret_keys_responses(): array {
		$valid_key = str_repeat( 'a', 64 );

		return array(
			'non-string input'                 => array( null ),
			'empty string'                     => array( '' ),
			'html error page'                  => array( "<html><body>502 Bad Gateway</body></html>\n" ),
			'fewer than eight lines'           => array( "define('AUTH_KEY',         '{$valid_key}');" ),
			'more than eight lines'            => array( self::build_response( $valid_key ) . "\ndefine('EXTRA_KEY', '{$valid_key}');" ),
			'value shorter than 64 chars'      => array( self::build_response( str_repeat( 'a', 63 ) ) ),
			'value longer than 64 chars'       => array( self::build_response( str_repeat( 'a', 65 ) ) ),
			'value containing single quote'    => array( self::build_response( substr_replace( $valid_key, "'", 10, 1 ) ) ),
			'value containing backslash'       => array( self::build_response( substr_replace( $valid_key, '\\', 10, 1 ) ) ),
			'value containing null byte'       => array( self::build_response( substr_replace( $valid_key, "\0", 10, 1 ) ) ),
			'value containing control char'    => array( self::build_response( substr_replace( $valid_key, "\t", 10, 1 ) ) ),
			'value containing non-ascii bytes' => array( self::build_response( str_repeat( 'a', 62 ) . 'é' ) ),
			'wrong constant order'             => array(
				strtr(
					self::build_response( $valid_key ),
					array(
						'AUTH_KEY'        => 'SECURE_AUTH_KEY',
						'SECURE_AUTH_KEY' => 'AUTH_KEY',
					)
				),
			),
		);
	}
}
