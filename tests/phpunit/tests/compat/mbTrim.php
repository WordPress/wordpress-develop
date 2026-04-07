<?php

/**
 * @group compat
 *
 * @covers ::mb_trim
 */
class Tests_Compat_mbTrim extends WP_UnitTestCase {

	/**
	 * @ticket 63804
	 *
	 * Test that mb_trim() is always available (either from PHP or WP).
	 */
	public function test_mb_trim_availability(): void {
		$this->assertTrue( function_exists( 'mb_trim' ) );
	}

	/**
	 * @ticket 63804
	 *
	 * @dataProvider data_mb_trim
	 *
	 * @param string      $input      The input string to be trimmed.
	 * @param string      $expected   The expected trimmed result.
	 * @param string|null $characters Optional. The characters to trim. Default null (whitespace).
	 * @param string|null $encoding   Optional. The character encoding. Default null (internal encoding).
	 */
	public function test_mb_trim( $input, $expected, $characters = null, $encoding = null ): void {
		$this->assertSame(
			$expected,
			mb_trim( $input, $characters, $encoding )
		);
	}

	/**
	 * Data provider for mb_trim tests.
	 *
	 * @return array[]
	 */
	public function data_mb_trim(): array {
		return array(
			// Basic ASCII whitespace.
			array( '  hello  ', 'hello' ),
			array( "\t\n\rhello\n\r\t", 'hello' ),
			// Unicode whitespace.
			array( "\u{00A0}hello\u{00A0}", 'hello' ),
			array( "\u{3000}hello\u{3000}", 'hello' ),
			array( "\u{00A0}\u{3000} hello \u{3000}\u{00A0}", 'hello' ),
			// Custom characters.
			array( 'xxhelloxx', 'hello', 'x' ),
			array( 'xyhelloyx', 'hello', 'xy' ),
			// No trimming needed.
			array( 'hello', 'hello' ),
			// Empty string.
			array( '', '' ),
			// With encoding.
			array( '  hello  ', 'hello', null, 'UTF-8' ),
			// Null characters.
			array( "\0hello\0", 'hello' ),
			// Vertical tab and form feed.
			array( "\v\fhello\f\v", 'hello' ),
		);
	}

	/**
	 * @ticket 63804
	 *
	 * Tests that passing a non-UTF-8 encoding to the WP polyfill triggers a
	 * warning and returns the original string unchanged, rather than attempting
	 * a lossy re-encoding that could silently corrupt data.
	 *
	 * Note: when PHP's native mb_trim() is available this test is skipped,
	 * because the native function does handle other encodings (via code-point
	 * boundary iteration, not re-encoding) and no warning is issued.
	 *
	 * @dataProvider data_mb_trim_non_utf8
	 *
	 * @param string $input    The input string to be trimmed.
	 * @param string $encoding The non-UTF-8 character encoding to pass.
	 */
	public function test_mb_trim_non_utf8_encoding_bails_with_warning( string $input, string $encoding ): void {
		if ( extension_loaded( 'mbstring' ) && version_compare( PHP_VERSION, '8.4', '>=' ) ) {
			$this->markTestSkipped( 'Native mb_trim() is available; polyfill bail-out behaviour does not apply.' );
		}

		$this->expectException( 'WP_Exception' );
		$this->expectExceptionMessage( 'mb_trim() polyfill only supports UTF-8 encoding' );

		// wp_trigger_error() raises E_USER_WARNING; convert it to an exception so
		// PHPUnit can catch it cleanly.
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( $encoding ): bool {
				if ( E_USER_WARNING === $errno ) {
					throw new WP_Exception( $errstr );
				}
				return false;
			},
			E_USER_WARNING
		);

		try {
			$result = mb_trim( $input, null, $encoding );

			// If wp_trigger_error() did not throw (e.g. errors are suppressed),
			// assert that the original string is returned unchanged.
			$this->assertSame( $input, $result, 'Polyfill should return the original string unchanged for unsupported encodings.' );
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * Data provider for non-UTF-8 encoding bail-out tests.
	 *
	 * @return array[]
	 */
	public function data_mb_trim_non_utf8(): array {
		return array(
			'ISO-8859-1 latin string' => array( ' café ', 'ISO-8859-1' ),
			'SJIS japanese string'    => array( ' test ', 'SJIS' ),
			'Windows-1252 string'     => array( ' hello ', 'Windows-1252' ),
		);
	}
}
