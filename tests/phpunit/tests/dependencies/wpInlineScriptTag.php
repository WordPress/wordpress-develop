<?php

/**
 * Test wp_get_inline_script_tag() and wp_print_inline_script_tag().
 *
 * @group dependencies
 * @group scripts
 * @covers ::wp_get_inline_script_tag
 * @covers ::wp_print_inline_script_tag
 */
class Tests_Dependencies_wpInlineScriptTag extends WP_UnitTestCase {

	private $original_theme_features = array();

	public function set_up() {
		global $_wp_theme_features;
		parent::set_up();
		$this->original_theme_features = $_wp_theme_features;
	}

	public function tear_down() {
		global $_wp_theme_features;
		$_wp_theme_features = $this->original_theme_features;
		parent::tear_down();
	}

	private $event_handler = <<<'JS'
document.addEventListener( 'DOMContentLoaded', function () {
	document.getElementById( 'elementID' )
			.addEventListener( 'click', function( event ) {
				event.preventDefault();
			});
});
JS;

	public function get_inline_script_tag_type_set() {
		$this->assertSame(
			'<script type="application/javascript" nomodule>' . "\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag(
				$this->event_handler,
				array(
					'type'     => 'application/javascript',
					'async'    => false,
					'nomodule' => true,
				)
			)
		);
	}

	public function test_get_inline_script_tag_type_not_set() {
		$this->assertSame(
			"<script nomodule>\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag(
				$this->event_handler,
				array(
					'async'    => false,
					'nomodule' => true,
				)
			)
		);
	}

	public function test_get_inline_script_tag_unescaped_src() {
		$this->assertSame(
			"<script>\n{$this->event_handler}\n</script>\n",
			wp_get_inline_script_tag( $this->event_handler )
		);
	}

	public function test_print_script_tag_prints_get_inline_script_tag() {
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes ) {
				if ( isset( $attributes['id'] ) && 'utils-js-extra' === $attributes['id'] ) {
					$attributes['async'] = true;
				}
				return $attributes;
			}
		);

		$attributes = array(
			'id'       => 'utils-js-before',
			'nomodule' => true,
		);

		$this->assertSame(
			wp_get_inline_script_tag( $this->event_handler, $attributes ),
			get_echo(
				'wp_print_inline_script_tag',
				array(
					$this->event_handler,
					$attributes,
				)
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
>
"script data";
</script>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_inline_script_tag(
				'"script data";',
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
<script test="test-a">
"script data";
</script>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_inline_script_tag(
				'"script data";',
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

	/**
	 * Test failure conditions setting inline script tag contents.
	 *
	 * @ticket 64500
	 */
	public function test_script_tag_dangerous_unescapeable_contents() {
		$this->setExpectedIncorrectUsage( 'wp_get_inline_script_tag' );
		/*
		 * </script> cannot be printed inside a script tag
		 * the `example/example` type is an unknown type with no known escaping rules.
		 * The only choice is to abort.
		 */
		$result = wp_get_inline_script_tag(
			'</script>',
			array( 'type' => 'example/example' )
		);
		$this->assertSame( '', $result );
	}
}
