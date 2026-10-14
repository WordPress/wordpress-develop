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
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_modifiable_text
	 */
	public function test_script_tag_dangerous_unescapeable_contents() {
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

	/**
	 * Test that the filter receives the script text as it is printed, with escaping applied.
	 *
	 * @ticket 66308
	 */
	public function test_filter_receives_escaped_script_text() {
		$filtered_data = null;
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes, $data ) use ( &$filtered_data ) {
				$filtered_data = $data;
				return $attributes;
			},
			10,
			2
		);

		$tag = wp_get_inline_script_tag( 'console.log( "</script>" );' );

		$this->assertSame( "\n" . 'console.log( "</\\u0073cript>" );' . "\n", $filtered_data );
		$this->assertSame( '<script>' . "\n" . 'console.log( "</\\u0073cript>" );' . "\n" . '</script>' . "\n", $tag );
	}

	/**
	 * Test that the filter receives the script text with newlines normalized as a browser parses them.
	 *
	 * @ticket 66308
	 */
	public function test_filter_receives_script_text_with_normalized_newlines() {
		$filtered_data = null;
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes, $data ) use ( &$filtered_data ) {
				$filtered_data = $data;
				return $attributes;
			},
			10,
			2
		);

		wp_get_inline_script_tag( "first();\r\nsecond();\rthird();" );

		$this->assertSame( "\nfirst();\nsecond();\nthird();\n", $filtered_data );
	}

	/**
	 * Test that the printed tag has exactly the attributes returned by the filter.
	 *
	 * @ticket 66308
	 */
	public function test_filter_replaces_attributes() {
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes ) {
				unset( $attributes['nomodule'] );
				$attributes['async'] = true;
				return $attributes;
			}
		);

		$expected = <<<'HTML'
<script id="example" async>
"script data";
</script>

HTML;

		$this->assertEqualHTML(
			$expected,
			wp_get_inline_script_tag(
				'"script data";',
				array(
					'id'       => 'example',
					'nomodule' => true,
				)
			)
		);
	}

	/**
	 * Test that a filter setting an unrecognized type on data that cannot be escaped for it returns no tag.
	 *
	 * @ticket 66308
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_modifiable_text
	 */
	public function test_filter_setting_unrecognized_type_on_dangerous_contents_returns_empty_string() {
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes ) {
				$attributes['type'] = 'example/example';
				return $attributes;
			}
		);

		$this->assertSame( '', wp_get_inline_script_tag( 'console.log( "</script>" );' ) );
	}

	/**
	 * Test that the filter does not run when no tag is printed.
	 *
	 * @ticket 66308
	 *
	 * @expectedIncorrectUsage WP_HTML_Tag_Processor::set_modifiable_text
	 */
	public function test_filter_not_called_for_dangerous_unescapeable_contents() {
		$filter_calls = 0;
		add_filter(
			'wp_inline_script_attributes',
			static function ( $attributes ) use ( &$filter_calls ) {
				++$filter_calls;
				return $attributes;
			}
		);

		$result = wp_get_inline_script_tag(
			'</script>',
			array( 'type' => 'example/example' )
		);

		$this->assertSame( '', $result );
		$this->assertSame( 0, $filter_calls );
	}
}
