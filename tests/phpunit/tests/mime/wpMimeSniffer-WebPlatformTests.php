<?php

/**
 * Unit tests covering the MIME Sniffing algorithm against the web-platform-tests MIME suite.
 *
 * This test suite runs a set of MIME sniffing tests using a third-party suite of test fixtures.
 * A third-party test suite allows WordPress’ behavior to be compared against an external
 * standard. Without a third party, there is risk of oversight or misinterpretation of the standard
 * being implemented in application code and in tests. web-platform-tests are maintained and used
 * by other projects (e.g. browsers) for the same purpose of validating behavior against an
 * external reference.
 *
 * @see fetch-wp-mimesniff-data.php for details on the third-party suite.
 *
 * @package WordPress
 * @subpackage MIME
 *
 * @since 7.0.0
 *
 * @group mime
 * @group web-platform-tests
 *
 * @coversDefaultClass WP_Mime_Sniffer
 */
class Tests_WpMimeSniffer_WebPlatformTests extends WP_UnitTestCase {
	/**
	 * Verifies parsing behavior of declared MIME types.
	 *
	 * @dataProvider data_mime_types
	 *
	 * @param $supplied_type
	 * @param $serialized
	 * @param $minimized
	 * @param $encoding
	 * @return void
	 */
	public function test_parses_declared_mime_types( string $supplied_type, ?string $serialized, ?string $minimized, ?string $encoding ) {
		$mime = WP_Mime_Sniffer::from_declaration( $supplied_type );

		if ( isset( $serialized ) ) {
			$this->assertNotNull(
				$mime,
				"Should have detected '{$minimized}' MIME type but failed to parse input."
			);
		} else {
			$this->assertNull(
				$mime,
				'Should have rejected unparsable input.'
			);
			return;
		}

		if ( isset( $encoding ) ) {
			$this->assertSame(
				self::visualize_controls( $encoding ),
				self::visualize_controls( $mime->get_indicated_charset() ?? '' ),
				'Mismatch in detected character encoding.'
			);
		}

		$this->assertEqualsIgnoringCase(
			self::visualize_controls( $serialized ),
			self::visualize_controls( $mime->serialize() ),
			'Mismatch in re-serialization of MIME type.'
		);

		if ( isset( $minimized ) ) {
			$this->assertSame(
				self::visualize_controls( $minimized ),
				self::visualize_controls( $mime->minimize() ),
				'Mismatch in "essence" of MIME type (content type without any parameters).'
			);
		}
	}

	/**
	 * Data provider.
	 *
	 * @return Generator
	 */
	public static function data_mime_types() {
		$test_file = file_get_contents( DIR_TESTDATA . '/mime/wpt-tests/mime-types.json' );
		$test_data = json_decode( $test_file, true );

		$test_group = null;
		$test_count = 0;
		foreach ( $test_data as $test_case ) {
			if ( is_string( $test_case ) ) {
				$test_group = $test_case;
				$test_count = 0;
				continue;
			}

			$label = self::visualize_controls( "{$test_group} {$test_count}: {$test_case['input']}" );

			yield $label => array(
				$test_case['input'],
				$test_case['output'],
				$test_case['minimizedMIMEType'] ?? null,
				$test_case['encoding'] ?? null,
			);

			++$test_count;
		}
	}

	/**
	 * Replaces control characters 0x00–0x1F, 0x7F with visual symbols for display.
	 */
	private static function visualize_controls( string $text ): string {
		return preg_replace_callback(
			'~[\x00-\x1F\x7F]~',
			static function ( $match ) {
				return mb_chr( ord( $match[0] ) + 0x2400 );
			},
			$text
		);
	}
}
