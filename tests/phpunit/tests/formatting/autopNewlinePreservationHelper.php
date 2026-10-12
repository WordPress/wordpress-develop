<?php

/**
 * @group formatting
 *
 * @covers ::_autop_newline_preservation_helper
 */
class Tests_Formatting_AutopNewlinePreservationHelper extends WP_UnitTestCase {

	/**
	 * Tests that newlines in the full pattern match are replaced with placeholders.
	 *
	 * @ticket 42058
	 *
	 * @dataProvider data_should_replace_newlines_with_placeholders
	 *
	 * @param array  $matches  A `preg_replace_callback()` matches array.
	 * @param string $expected The expected function output.
	 */
	public function test_should_replace_newlines_with_placeholders( $matches, $expected ) {
		$this->assertSame( $expected, _autop_newline_preservation_helper( $matches ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_replace_newlines_with_placeholders() {
		return array(
			'an empty string'                => array(
				'matches'  => array( '' ),
				'expected' => '',
			),
			'a string without newlines'      => array(
				'matches'  => array( '<script>var a = 1;</script>' ),
				'expected' => '<script>var a = 1;</script>',
			),
			'a single newline'               => array(
				'matches'  => array( "\n" ),
				'expected' => '<WPPreserveNewline />',
			),
			'consecutive newlines'           => array(
				'matches'  => array( "\n\n\n" ),
				'expected' => '<WPPreserveNewline /><WPPreserveNewline /><WPPreserveNewline />',
			),
			'newlines within a script tag'   => array(
				'matches'  => array( "<script>\nvar a = 1;\n</script>" ),
				'expected' => '<script><WPPreserveNewline />var a = 1;<WPPreserveNewline /></script>',
			),
			'newlines within a style tag'    => array(
				'matches'  => array( "<style>\n.wp { color: red; }\n</style>" ),
				'expected' => '<style><WPPreserveNewline />.wp { color: red; }<WPPreserveNewline /></style>',
			),
			'a literal backslash-n sequence' => array(
				'matches'  => array( '\n' ),
				'expected' => '\n',
			),
			'whitespace other than newlines' => array(
				'matches'  => array( "a\t \x0Bb" ),
				'expected' => "a\t \x0Bb",
			),
			'an existing placeholder'        => array(
				'matches'  => array( "<WPPreserveNewline />\n" ),
				'expected' => '<WPPreserveNewline /><WPPreserveNewline />',
			),
		);
	}

	/**
	 * Tests that only line feeds are replaced, and carriage returns are left as-is.
	 *
	 * wpautop() standardizes newline characters to "\n" before calling this helper,
	 * so a carriage return should not reach the helper in practice.
	 *
	 * @ticket 42058
	 *
	 * @dataProvider data_should_only_replace_line_feeds
	 *
	 * @param array  $matches  A `preg_replace_callback()` matches array.
	 * @param string $expected The expected function output.
	 */
	public function test_should_only_replace_line_feeds( $matches, $expected ) {
		$this->assertSame( $expected, _autop_newline_preservation_helper( $matches ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_only_replace_line_feeds() {
		return array(
			'a carriage return'             => array(
				'matches'  => array( "a\rb" ),
				'expected' => "a\rb",
			),
			'a carriage return + line feed' => array(
				'matches'  => array( "a\r\nb" ),
				'expected' => "a\r<WPPreserveNewline />b",
			),
			'a line feed + carriage return' => array(
				'matches'  => array( "a\n\rb" ),
				'expected' => "a<WPPreserveNewline />\rb",
			),
		);
	}

	/**
	 * Tests that only the full pattern match is used and capture groups are ignored.
	 *
	 * @ticket 42058
	 */
	public function test_should_ignore_capture_groups() {
		$matches = array(
			"<svg>\n<path d=\"M0 0\" />\n</svg>",
			"svg\n",
		);

		$this->assertSame(
			'<svg><WPPreserveNewline /><path d="M0 0" /><WPPreserveNewline /></svg>',
			_autop_newline_preservation_helper( $matches )
		);
	}

	/**
	 * Tests the helper as the `preg_replace_callback()` callback that wpautop() uses it as.
	 *
	 * Newlines inside the matched element should be replaced, while newlines
	 * elsewhere in the text should be left alone.
	 *
	 * @ticket 42058
	 *
	 * @dataProvider data_should_preserve_newlines_as_a_preg_replace_callback
	 *
	 * @param string $text     The text to run the callback against.
	 * @param string $expected The expected result.
	 */
	public function test_should_preserve_newlines_as_a_preg_replace_callback( $text, $expected ) {
		$actual = preg_replace_callback(
			'/<(script|style|svg|math).*?<\/\\1>/s',
			'_autop_newline_preservation_helper',
			$text
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_preserve_newlines_as_a_preg_replace_callback() {
		return array(
			'a script element'               => array(
				'text'     => "<script>\nvar a = 1;\n</script>",
				'expected' => '<script><WPPreserveNewline />var a = 1;<WPPreserveNewline /></script>',
			),
			'a style element'                => array(
				'text'     => "<style>\n.wp { color: red; }\n</style>",
				'expected' => '<style><WPPreserveNewline />.wp { color: red; }<WPPreserveNewline /></style>',
			),
			'an svg element'                 => array(
				'text'     => "<svg>\n<path d=\"M0 0\" />\n</svg>",
				'expected' => '<svg><WPPreserveNewline /><path d="M0 0" /><WPPreserveNewline /></svg>',
			),
			'a math element'                 => array(
				'text'     => "<math>\n<mi>a</mi>\n</math>",
				'expected' => '<math><WPPreserveNewline /><mi>a</mi><WPPreserveNewline /></math>',
			),
			'an element with attributes'     => array(
				'text'     => "<script type=\"text/javascript\">\nvar a = 1;\n</script>",
				'expected' => '<script type="text/javascript"><WPPreserveNewline />var a = 1;<WPPreserveNewline /></script>',
			),
			'newlines outside of an element' => array(
				'text'     => "Line one.\n<script>var a = 1;</script>\nLine two.",
				'expected' => "Line one.\n<script>var a = 1;</script>\nLine two.",
			),
			'multiple elements'              => array(
				'text'     => "<style>\n.wp {}\n</style>\n\n<script>\nvar a = 1;\n</script>",
				'expected' => "<style><WPPreserveNewline />.wp {}<WPPreserveNewline /></style>\n\n<script><WPPreserveNewline />var a = 1;<WPPreserveNewline /></script>",
			),
			'text with no matching elements' => array(
				'text'     => "Line one.\nLine two.",
				'expected' => "Line one.\nLine two.",
			),
			'an unclosed element'            => array(
				'text'     => "<script>\nvar a = 1;",
				'expected' => "<script>\nvar a = 1;",
			),
			'an element with no newlines'    => array(
				'text'     => '<script>var a = 1;</script>',
				'expected' => '<script>var a = 1;</script>',
			),
		);
	}
}
