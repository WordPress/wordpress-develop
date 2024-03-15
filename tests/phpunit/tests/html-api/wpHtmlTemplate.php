<?php
/**
 * Unit tests covering WP_HTML_Template functionality.
 *
 * @package WordPress
 * @subpackage HTML-API
 *
 * @since 6.5.0
 *
 * @group html-api
 *
 * @coversDefaultClass WP_HTML_Template
 */

class Tests_HtmlApi_WpHtmlTemplate extends WP_UnitTestCase {
	/**
	 * Demonstrates how to pass values into an HTML template.
	 *
	 * @ticket 60229
	 */
	public function test_basic_render() {
		$html = WP_HTML_Template::render(
			'<div class="is-test </%class>" ...div-args inert="</%is_inert>">Just a </%count> test</div>',
			array(
				'count'    => '<strong>Hi <3</strong>',
				'class'    => '5>4',
				'is_inert' => 'inert',
				'div-args' => array(
					'class'    => 'hoover',
					'disabled' => true,
				),
			)
		);

		$this->assertSame(
			'<div disabled class="hoover"  inert="inert">Just a &lt;strong&gt;Hi &lt;3&lt;/strong&gt; test</div>',
			$html,
			'Failed to properly render template.'
		);
	}

	/**
	 * Ensures that basic attacks on attribute names and values are blocked.
	 *
	 * @ticket 60229
	 *
	 * @covers WP_HTML::render
	 */
	public function test_cannot_break_out_of_tag_with_malicious_attribute_name() {
		$html = WP_HTML_Template::render(
			'<div class="</%class>" ...args>',
			array(
				'class' => '"><script>alert("hi")</script>',
				'args'  => array(
					'"> double-quoted escape' => 'busted!',
					'> tag escape'            => 'busted!',
				),
			)
		);

		// The output here should include an escaped `class` attribute and no others, also no other tags.
		$processor = new WP_HTML_Tag_Processor( $html );
		$processor->next_tag();

		$this->assertSame(
			'DIV',
			$processor->get_tag(),
			"Expected to find DIV tag but found {$processor->get_tag()} instead."
		);

		$this->assertSame(
			'"><script>alert("hi")</script>',
			$processor->get_attribute( 'class' ),
			'Should have found escaped `class` attribute.'
		);

		$this->assertSame(
			array( 'class' ),
			$processor->get_attribute_names_with_prefix( '' ),
			'Should have set `class` attribute and no others.'
		);

		$this->assertFalse(
			$processor->next_tag(),
			"Should not have found any other tags but found {$processor->get_tag()} instead."
		);
	}

	/**
	 * Ensures that basic replacement inside a TEXTAREA subtitutes placeholders.
	 *
	 * @ticket 60229
	 */
	public function test_replaces_textarea_placeholders() {
		$html = WP_HTML_Template::render(
			'<textarea>The </%big> one.</textarea>',
			array( 'big' => '<HUGE> (</textarea>)' )
		);

		$this->assertSame(
			'<textarea>The <HUGE> (&lt;/textarea>) one.</textarea>',
			$html,
			'Should have replaced placeholder with RCDATA escaping rules.'
		);
	}
}
