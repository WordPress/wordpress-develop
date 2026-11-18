<?php

/**
 * @group admin
 *
 * @covers ::wp_get_submitted_date_time_format
 */
class Tests_Admin_Includes_Misc_WpGetSubmittedDateTimeFormat_Test extends WP_UnitTestCase {

	/**
	 * Tests wp_get_submitted_date_time_format() against raw (slashed) values,
	 * as they would actually arrive via `$_POST` after WP's magic-quotes layer.
	 *
	 * @ticket 66142
	 *
	 * @dataProvider data_wp_get_submitted_date_time_format
	 *
	 * @param string      $submitted_format Unslashed submitted format radio value.
	 * @param string|null $custom_format    Unslashed submitted custom format value, or null if absent.
	 * @param string|null $expected         Expected resolved value, unslashed.
	 */
	public function test_wp_get_submitted_date_time_format( $submitted_format, $custom_format, $expected ) {
		$raw_submitted_format = wp_slash( $submitted_format );
		$raw_custom_format    = null === $custom_format ? null : wp_slash( $custom_format );

		$actual = wp_get_submitted_date_time_format( $raw_submitted_format, $raw_custom_format );

		$this->assertSame( $expected, null === $actual ? null : wp_unslash( $actual ) );
	}

	/**
	 * @return array<string, array{
	 *     submitted_format: string,
	 *     custom_format:    string|null,
	 *     expected:         string|null,
	 * }>
	 */
	public function data_wp_get_submitted_date_time_format(): array {
		return array(
			'non-custom preset format is passed through unchanged'          => array(
				'submitted_format' => 'F j, Y',
				'custom_format'    => null,
				'expected'         => 'F j, Y',
			),
			'custom selected with a value uses the custom value'            => array(
				'submitted_format' => '\c\u\s\t\o\m',
				'custom_format'    => 'Y-m-d',
				'expected'         => 'Y-m-d',
			),
			'custom selected with an empty value is left unchanged'         => array(
				'submitted_format' => '\c\u\s\t\o\m',
				'custom_format'    => '',
				'expected'         => null,
			),
			'custom selected with a whitespace-only value is left unchanged' => array(
				'submitted_format' => '\c\u\s\t\o\m',
				'custom_format'    => '   ',
				'expected'         => null,
			),
			'custom selected without a custom field submitted at all'       => array(
				'submitted_format' => '\c\u\s\t\o\m',
				'custom_format'    => null,
				'expected'         => '\c\u\s\t\o\m',
			),
			'custom value with escaped literal characters is preserved'    => array(
				'submitted_format' => '\c\u\s\t\o\m',
				'custom_format'    => 'H\h i\m',
				'expected'         => 'H\h i\m',
			),
		);
	}
}
