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
		$input = '<p>Safe</p><script>alert(1)</script>';

		$this->assertSame(
			wp_kses_post( $input ),
			wp_kses_post_deep( $input )
		);
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
			'title'   => wp_kses_post( $input['title'] ),
			'nested'  => array(
				'body' => wp_kses_post( $input['nested']['body'] ),
			),
			'allowed' => wp_kses_post( $input['allowed'] ),
		);

		$this->assertSame( $expected, wp_kses_post_deep( $input ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_sanitizes_object_string_properties() {
		$input       = new stdClass();
		$input->html = '<p>Ok</p><script>bad</script>';

		$result = wp_kses_post_deep( $input );

		$this->assertSame( wp_kses_post( $input->html ), $result->html );
	}
}
