<?php

/**
 * Tests for wp_kses_post_deep().
 *
 * @group kses
 *
 * @covers ::wp_kses_post_deep
 */
class Tests_Kses_wpKsesPostDeep extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 */
	public function test_sanitizes_string_values() {
		$input  = '<p>Safe</p><script>alert(1)</script>';
		$result = wp_kses_post_deep( $input );

		$this->assertSame( '<p>Safe</p>', $result );
	}

	/**
	 * @ticket 65819
	 */
	public function test_sanitizes_nested_array_values() {
		$input = array(
			'title'   => '<strong>Ok</strong><script>bad</script>',
			'nested'  => array(
				'body' => '<p>Hello</p><iframe src="https://example.com"></iframe>',
			),
			'allowed' => '<em>emphasis</em>',
		);

		$expected = array(
			'title'   => '<strong>Ok</strong>',
			'nested'  => array(
				'body' => '<p>Hello</p>',
			),
			'allowed' => '<em>emphasis</em>',
		);

		$result = wp_kses_post_deep( $input );

		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 65819
	 */
	public function test_sanitizes_object_string_properties() {
		$input       = new stdClass();
		$input->html = '<p>Ok</p><script>bad</script>';

		$result = wp_kses_post_deep( $input );

		$this->assertSame( '<p>Ok</p>', $result->html );
	}
}
