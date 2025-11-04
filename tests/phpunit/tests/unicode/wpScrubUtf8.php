<?php
/**
 * Unit tests covering WordPress’ UTF-8 handling.
 *
 * @package WordPress
 * @group unicode
 */

class Tests_WpScrubUtf8 extends WP_UnitTestCase {
	/**
	 * Verifies that WordPress can properly detect valid and invalid UTF-8.
	 *
	 * @ticket 63837
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string      $bytes    Bytes as a PHP string.
	 * @param string|null $scrubbed Expected checked value, if string isn’t valid UTF-8.
	 */
	public function test_properly_checks_utf8( string $bytes, ?string $scrubbed = null ) {
		if ( null === $scrubbed ) {
			$this->assertSame(
				$bytes,
				wp_check_invalid_utf8( $bytes ),
				'Should have returned the unchanged string for valid UTF-8 input when not stripping invalid bytes.'
			);

			$this->assertSame(
				$bytes,
				wp_check_invalid_utf8( $bytes, true ),
				'Should have returned the unchanged string for valid UTF-8 input when stripping invalid bytes.'
			);
		} else {
			$this->assertSame(
				'',
				wp_check_invalid_utf8( $bytes ),
				'Should have rejected invalid input, returning an empty string when not stripping invalid bytes.'
			);

			$this->assertSame(
				$scrubbed,
				wp_check_invalid_utf8( $bytes, true ),
				'Failed to properly scrub the invalid spans of UTF-8 from the input string.'
			);
		}
	}

	/**
	 * Verifies that WordPress can properly detect valid UTF-8 while replacing invalid byte sequences.
	 *
	 * @ticket 63837
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string      $bytes    Bytes as a PHP string.
	 * @param string|null $scrubbed Expected checked value, if string isn’t valid UTF-8.
	 */
	public function test_properly_scrubs_utf8( string $bytes, ?string $scrubbed = null ) {
		if ( null === $scrubbed ) {
			$this->assertSame(
				$bytes,
				wp_scrub_utf8( $bytes ),
				'Should have returned the unchanged string for valid UTF-8 input.'
			);
		} else {
			$this->assertSame(
				bin2hex( $scrubbed ),
				bin2hex( wp_scrub_utf8( $bytes ) ),
				'Failed to properly scrub the invalid spans of UTF-8 from the input string.'
			);
		}
	}

	/**
	 * Verifies that WordPress’ fallback code can properly detect valid UTF-8
	 * while replacing invalid byte sequences.
	 *
	 * @ticket 63837
	 *
	 * @dataProvider data_utf8_test_data
	 *
	 * @param string      $bytes    Bytes as a PHP string.
	 * @param string|null $scrubbed Expected checked value, if string isn’t valid UTF-8.
	 */
	public function test_fallback_properly_checks_utf8( string $bytes, ?string $scrubbed = null ) {
		if ( null === $scrubbed ) {
			$this->assertSame(
				$bytes,
				_wp_scrub_utf8_fallback( $bytes ),
				'Should have returned the unchanged string for valid UTF-8 input.'
			);
		} else {
			$this->assertSame(
				bin2hex( $scrubbed ),
				bin2hex( _wp_scrub_utf8_fallback( $bytes ) ),
				'Failed to properly scrub the invalid spans of UTF-8 from the input string.'
			);
		}
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
		$line_number      = 0;
		$last_description = '';

		while ( false !== ( $line = fgets( $test_file ) ) ) {
			++$line_number;

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
					yield "{$reference} {$last_description}" => array( $test_data, null );
					break;

				case 'valid hex':
				case 'invalid hex':
					if ( 'invalid hex' === $classification && count( $test_parts ) < 5 ) {
						throw new Exception( "Test data missing expected “scrubbed” value: check utf8tests.txt:{$line_number}" );
					}

					$bytes    = hex2bin( str_replace( ' ', '', $test_data ) );
					$scrubbed = 'invalid hex' === $classification
						? hex2bin( str_replace( ' ', '', trim( $test_parts[4] ) ) )
						: null;

					yield "{$reference} {$last_description}" => array( $bytes, $scrubbed );
					break;

				default:
					throw new Exception( "Test input file contains unrecognized input classification '{$classification}' (see utf8tests.txt): {$line}" );
			}
		}
	}
}
