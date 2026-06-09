<?php

/**
 * Test wp_get_script_tag() and wp_print_script_tag().
 *
 * @group dependencies
 * @group scripts
 */
class Tests_Dependencies_wpScriptTag extends WP_UnitTestCase {

	public function get_script_tag_type_set() {
		$this->assertEqualHTML(
			'<script src="https://localhost/PATH/FILE.js" type="application/javascript" nomodule></script>' . "\n",
			wp_get_script_tag(
				array(
					'type'     => 'application/javascript',
					'src'      => 'https://localhost/PATH/FILE.js',
					'async'    => false,
					'nomodule' => true,
				)
			)
		);
	}

	/**
	 * @covers ::wp_get_script_tag
	 */
	public function test_get_script_tag_type_not_set() {
		$this->assertEqualHTML(
			'<script src="https://localhost/PATH/FILE.js" nomodule></script>' . "\n",
			wp_get_script_tag(
				array(
					'src'      => 'https://localhost/PATH/FILE.js',
					'async'    => false,
					'nomodule' => true,
				)
			)
		);
	}

	/**
	 * @covers ::wp_print_script_tag
	 */
	public function test_print_script_tag_prints_get_script_tag() {
		add_filter(
			'wp_script_attributes',
			static function ( $attributes ) {
				if ( isset( $attributes['id'] ) && 'utils-js-extra' === $attributes['id'] ) {
					$attributes['async'] = true;
				}
				return $attributes;
			}
		);

		$attributes = array(
			'src'      => 'https://localhost/PATH/FILE.js',
			'id'       => 'utils-js-extra',
			'nomodule' => true,
		);

		$this->assertEqualHTML(
			wp_get_script_tag( $attributes ),
			get_echo(
				'wp_print_script_tag',
				array( $attributes )
			)
		);
	}

	/**
	 * Test the behavior of generated script tag attributes passed different values and types of values.
	 *
	 * @ticket 64500
	 */
	public function test_script_tag_attribute_value_types() {
		$expected = <<<'HTML'
<script
	true
	null
	empty-string=""
	0-string="0"
	1-string="1"
	0-numeric="0"
	1-numeric="1"
></script>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_script_tag(
				array(
					'true'         => true,
					'false'        => false,
					'null'         => null,
					'empty-string' => '',
					'0-string'     => '0',
					'1-string'     => '1',
					'0-numeric'    => 0,
					'1-numeric'    => 1,
				)
			),
		);
	}

	/**
	 * Test the behavior of generated script tag repeated attributes.
	 *
	 * HTML will ignore case-insensitive repeated attributes. Ensure that the handling of input
	 * attributes aligns with expectations.
	 *
	 * @ticket 64500
	 */
	public function test_script_tag_repeat_attributes() {
		$expected = <<<'HTML'
<script test="test-a"></script>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_script_tag(
				array(
					'test' => 'test-a',
					'tesT' => 'tesT-b',
					'teST' => 'teST-c',
					'tEST' => 'tEST-d',
					'TEST' => 'TEST-e',
				)
			),
		);
	}
}
