<?php
/**
 * Unit tests covering WordPress’ UTF-8 handling.
 *
 * @package WordPress
 * @group unicode
 */

class Tests_WpIsValidUtf8TestCase extends WP_UnitTestCase {
	/**
	 * Verifies that WordPress can properly detect valid and invalid UTF-8.
	 *
	 * @ticket 38044
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string $bytes Bytes as a PHP string.
	 */
	public function test_properly_validates_utf8( string $bytes ) {
		$is_valid = mb_check_encoding( $bytes, 'UTF-8' );

		$this->assertSame(
			$is_valid,
			wp_is_valid_utf8( $bytes ),
			$is_valid
				? 'Should have identified the input as a valid UTF-8 string.'
				: 'Should have reject the invalid UTF-8 string.'
		);
	}

	/**
	 * Verifies that WordPress can properly detect valid and invalid UTF-8;
	 * forces testing with the fallback mechanism in pure PHP code.
	 *
	 * @ticket 38044
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string $bytes Bytes as a PHP string.
	 */
	public function test_fallback_properly_validates_utf8( string $bytes ) {
		$is_valid = mb_check_encoding( $bytes, 'UTF-8' );

		$this->assertSame(
			$is_valid,
			_wp_is_valid_utf8_fallback( $bytes ),
			$is_valid
				? 'Should have identified the input as a valid UTF-8 string.'
				: 'Should have reject the invalid UTF-8 string.'
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
		$test_file        = fopen( __DIR__ . '/../../data/unicode/utf8tests/utf8tests.txt', 'r' );
		$last_description = '';

		while ( false !== ( $line = fgets( $test_file ) ) ) {
			if ( empty( trim( $line ) ) ) {
				continue;
			}

			if ( str_starts_with( $line, '#' ) ) {
				$last_description = trim( substr( $line, 1 ) );
				continue;
			}

			$test_parts = explode( ':', $line );
			if ( count( $test_parts ) < 3 ) {
				throw new Exception( 'Wrong test data: check utf8tests.txt' );
			}

			list( $reference, $classification, $test_data ) = $test_parts;

			$reference      = trim( $reference );
			$classification = trim( $classification );
			$test_data      = trim( $test_data );

			switch ( $classification ) {
				case 'valid':
					yield "{$reference} {$last_description}" => array( $test_data );
					break;

				case 'valid hex':
				case 'invalid hex':
					$bytes = hex2bin( str_replace( ' ', '', $test_data ) );
					yield "{$reference} {$last_description}" => array( $bytes );
					break;

				default:
					throw new Exception( "Test input file contains unrecognized input classification '{$classification}' (see utf8tests.txt): {$line}" );
			}
		}
	}
}
