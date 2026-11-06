<?php

/**
 * Tests for the apply_shortcodes() function.
 *
 * @group shortcode
 *
 * @covers ::apply_shortcodes
 */
class Tests_Shortcodes_ApplyShortcodes extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_shortcode( 'apply-greeting', array( $this, 'shortcode_greeting' ) );
	}

	public function tear_down() {
		remove_shortcode( 'apply-greeting' );

		parent::tear_down();
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Content.
	 * @return string
	 */
	public function shortcode_greeting( $atts, $content = null ) {
		$atts = shortcode_atts( array( 'name' => 'World' ), $atts );

		return 'Hello ' . $atts['name'] . ( '' !== (string) $content ? ' [' . $content . ']' : '' );
	}

	/**
	 * Tests that the shortcodes are processed.
	 *
	 * @ticket 66500
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content  Content with shortcodes.
	 * @param string $expected Expected content.
	 */
	public function test_should_process_the_shortcodes( $content, $expected ) {
		$this->assertSame( $expected, apply_shortcodes( $content ) );
	}

	/**
	 * Tests that the result is the same as the one of do_shortcode().
	 *
	 * @ticket 66500
	 *
	 * @dataProvider data_content
	 *
	 * @param string $content Content with shortcodes.
	 */
	public function test_should_return_the_same_result_as_do_shortcode( $content ) {
		$this->assertSame( do_shortcode( $content ), apply_shortcodes( $content ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_content() {
		return array(
			'self closing'             => array( 'A [apply-greeting /] B', 'A Hello World B' ),
			'with an attribute'        => array( '[apply-greeting name="Bob"]', 'Hello Bob' ),
			'enclosing content'        => array( '[apply-greeting]text[/apply-greeting]', 'Hello World [text]' ),
			'escaped shortcode'        => array( '[[apply-greeting]]', '[apply-greeting]' ),
			'unregistered shortcode'   => array( '[apply-unknown]', '[apply-unknown]' ),
			'content without brackets' => array( 'No shortcodes here', 'No shortcodes here' ),
			'empty string'             => array( '', '' ),
		);
	}

	/**
	 * Tests that the $ignore_html argument is passed on, so that shortcodes inside HTML elements are skipped.
	 *
	 * @ticket 66500
	 */
	public function test_should_skip_the_shortcodes_inside_html_when_ignoring_html() {
		$content = '<a title="[apply-greeting]">[apply-greeting]</a>';

		$this->assertSame( '<a title="[apply-greeting]">Hello World</a>', apply_shortcodes( $content, true ) );
	}

	/**
	 * Tests that the shortcodes inside HTML elements are processed by default.
	 *
	 * @ticket 66500
	 */
	public function test_should_process_the_shortcodes_inside_html_by_default() {
		$content = '<a title="[apply-greeting]">[apply-greeting]</a>';

		$this->assertSame( '<a title="Hello World">Hello World</a>', apply_shortcodes( $content ) );
		$this->assertSame( '<a title="Hello World">Hello World</a>', apply_shortcodes( $content, false ) );
	}
}
