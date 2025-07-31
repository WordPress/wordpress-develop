<?php
/**
 * Unit tests covering WordPress’ UTF-8 handling.
 *
 * @package WordPress
 * @subpackage Unicode
 */

class Tests_WpIsValidUtf8TestCase extends WP_UnitTestCase {
	/**
	 * Verifies that WordPress can properly detect valid and invalid UTF-8.
	 *
	 * Ticket {WP_TICKET}
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string $bytes Bytes as a PHP string.
	 */
	public function test_properly_validates_utf8( $bytes ) {
		$is_valid = mb_check_encoding( $bytes, 'UTF-8' );

		$this->assertSame(
			$is_valid,
			wp_is_valid_utf8( $bytes ),
			$is_valid
				? 'Should have identified the input as a valid UTF-8 string.'
				: 'Should have rejected the input as a valid UTF-8 string.'
		);
	}

	/**
	 * Verifies that WordPress can approximate valid and invalid UTF-8.
	 *
	 * Ticket {WP_TICKET}
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string $bytes Bytes as a PHP string.
	 */
	public function test_seems_like_utf8( $bytes ) {
		$is_valid = mb_check_encoding( $bytes, 'UTF-8' );

		$this->assertSame(
			$is_valid,
			seems_utf8( $bytes ),
			$is_valid
				? 'Should have identified the input as a valid UTF-8 string.'
				: 'Should have rejected the input as a valid UTF-8 string.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @throws Exception
	 *
	 * @return Generator
	 */
	public static function data_utf8_test_data() {
		$test_file = fopen( __DIR__ . '/../../data/unicode/utf8tests.txt', 'r' );

		while ( false !== ( $line = fgets( $test_file ) ) ) {
			if ( empty( trim( $line ) ) || str_starts_with( $line, '#' ) ) {
				continue;
			}

			$test_parts = explode( ':', $line );
			if ( count( $test_parts ) < 3 ) {
				throw new Exception( 'Wrong test data: check utf8tests.txt' );
			}

			list( $reference, $classification, $test_data ) = $test_parts;

			$reference = trim( $reference );
			$test_data = trim( $test_data );

			switch ( $classification ) {
				case 'valid':
					yield "{$reference}: {$test_data}" => array( $test_data );
					break;

				case 'valid hex':
				case 'invalid hex':
					$bytes = hex2bin( str_replace( ' ', '', $test_data ) );
					yield "{$reference}: {$test_data}" => array( $bytes );
					break;
			}
		}
	}
}
