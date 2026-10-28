<?php

/**
 * Tests for wp_kses_decode_entities().
 *
 * Prefer WP_HTML_Decoder::decode_attribute() for new code. It understands the
 * full character-reference grammar (including named references) and does not
 * rely on single-byte chr() decoding. These tests only document the helper's
 * historical behavior: numeric entities are decoded, named entities are left
 * unchanged. That named-entity gap was tolerated for legacy URL-protocol
 * checks, not because named decoding is undesirable.
 *
 * @group kses
 *
 * @covers ::wp_kses_decode_entities
 */
class Tests_Kses_wpKsesDecodeEntities extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_wp_kses_decode_entities
	 *
	 * @param string $content  Content with entities.
	 * @param string $expected Expected decoded content.
	 */
	public function test_wp_kses_decode_entities( $content, $expected ) {
		$this->assertSame( $expected, wp_kses_decode_entities( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_wp_kses_decode_entities() {
		return array(
			'plain text'             => array( 'hello', 'hello' ),
			'decimal entity'         => array( '&#65;', 'A' ),
			'hex entity lowercase x' => array( '&#x41;', 'A' ),
			'hex entity uppercase X' => array( '&#X41;', 'A' ),
			'mixed content'          => array( 'A&#66;&#x43;D', 'ABCD' ),
			'leaves named entities'  => array( '&amp;', '&amp;' ),
		);
	}
}
