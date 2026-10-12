<?php
/**
 * Tests for _wp_connectors_mask_api_key().
 *
 * @group connectors
 * @covers ::_wp_connectors_mask_api_key
 */
class Tests_Connectors_WpConnectorsMaskApiKey extends WP_UnitTestCase {

	/**
	 * Tests that API keys are masked correctly.
	 *
	 * @ticket 64730
	 *
	 * @dataProvider data_mask_api_key
	 *
	 * @param string $input    API key to mask.
	 * @param string $expected Expected masked result.
	 */
	public function test_mask_api_key( string $input, string $expected ) {
		$this->assertSame( $expected, _wp_connectors_mask_api_key( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type string $input    API key to mask.
	 *     @type string $expected Expected masked result.
	 * }
	 */
	public function data_mask_api_key(): array {
		$bullet = "\u{2022}";

		return array(
			'empty string'                 => array( '', '' ),
			'1 char'                       => array( 'a', 'a' ),
			'4 chars (boundary)'           => array( 'abcd', 'abcd' ),
			'5 chars (1 bullet + last 4)'  => array( 'abcde', $bullet . 'bcde' ),
			'20 chars (cap at 16 bullets)' => array( '12345678901234567890', str_repeat( $bullet, 16 ) . '7890' ),
			'30 chars (cap at 16 bullets)' => array( str_repeat( 'x', 30 ), str_repeat( $bullet, 16 ) . 'xxxx' ),
		);
	}
}
