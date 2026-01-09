<?php
/**
 * Test wp_kses_hair() function.
 *
 * @group kses
 */
class Tests_Kses_WpKsesHair extends WP_UnitTestCase {

	/**
	 * Standard allowed protocols for testing.
	 *
	 * @var array
	 */
	protected $allowed_protocols;

	/**
	 * Set up before each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->allowed_protocols = wp_allowed_protocols();
	}

	/**
	 * Test wp_kses_hair() with various attribute patterns.
	 *
	 * @ticket 63724
	 * @dataProvider data_attribute_parsing
	 * @covers wp_kses_hair
	 */
	public function test_attribute_parsing( string $input, array $expected ) {
		$result = wp_kses_hair( $input, $this->allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for attribute parsing tests.
	 *
	 * @return Generator
	 */
	public function data_attribute_parsing() {
		// Basic Attribute Parsing.
		yield 'single attribute with double quotes' => array(
			'class="test-class"',
			array(
				'class' => array(
					'name'  => 'class',
					'value' => 'test-class',
					'whole' => 'class="test-class"',
					'vless' => 'n',
				),
			),
		);

		yield 'single attribute with single quotes' => array(
			"title='My Title'",
			array(
				'title' => array(
					'name'  => 'title',
					'value' => 'My Title',
					'whole' => "title='My Title'",
					'vless' => 'n',
				),
			),
		);

		yield 'unquoted attribute value' => array(
			'id=test123',
			array(
				'id' => array(
					'name'  => 'id',
					'value' => 'test123',
					'whole' => 'id="test123"',
					'vless' => 'n',
				),
			),
		);

		yield 'multiple attributes' => array(
			'class="btn" id="submit-btn" data-value="123"',
			array(
				'class'      => array(
					'name'  => 'class',
					'value' => 'btn',
					'whole' => 'class="btn"',
					'vless' => 'n',
				),
				'id'         => array(
					'name'  => 'id',
					'value' => 'submit-btn',
					'whole' => 'id="submit-btn"',
					'vless' => 'n',
				),
				'data-value' => array(
					'name'  => 'data-value',
					'value' => '123',
					'whole' => 'data-value="123"',
					'vless' => 'n',
				),
			),
		);

		yield 'valueless attributes' => array(
			'disabled required checked',
			array(
				'disabled' => array(
					'name'  => 'disabled',
					'value' => '',
					'whole' => 'disabled',
					'vless' => 'y',
				),
				'required' => array(
					'name'  => 'required',
					'value' => '',
					'whole' => 'required',
					'vless' => 'y',
				),
				'checked'  => array(
					'name'  => 'checked',
					'value' => '',
					'whole' => 'checked',
					'vless' => 'y',
				),
			),
		);

		yield 'valueless attribute at end' => array(
			'type="checkbox" checked',
			array(
				'type'    => array(
					'name'  => 'type',
					'value' => 'checkbox',
					'whole' => 'type="checkbox"',
					'vless' => 'n',
				),
				'checked' => array(
					'name'  => 'checked',
					'value' => '',
					'whole' => 'checked',
					'vless' => 'y',
				),
			),
		);

		yield 'mixed valued and valueless' => array(
			'disabled class="form-control" readonly id=input1',
			array(
				'disabled' => array(
					'name'  => 'disabled',
					'value' => '',
					'whole' => 'disabled',
					'vless' => 'y',
				),
				'class'    => array(
					'name'  => 'class',
					'value' => 'form-control',
					'whole' => 'class="form-control"',
					'vless' => 'n',
				),
				'readonly' => array(
					'name'  => 'readonly',
					'value' => '',
					'whole' => 'readonly',
					'vless' => 'y',
				),
				'id'       => array(
					'name'  => 'id',
					'value' => 'input1',
					'whole' => 'id="input1"',
					'vless' => 'n',
				),
			),
		);

		// Character Reference Handling.
		yield 'named entities' => array(
			'title="&lt;Hello&gt; &amp; &quot;World&quot;"',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => '&lt;Hello&gt; &amp; &quot;World&quot;',
					'whole' => 'title="&lt;Hello&gt; &amp; &quot;World&quot;"',
					'vless' => 'n',
				),
			),
		);

		yield 'numeric decimal entities' => array(
			'title="&#60;test&#62;"',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => '&#60;test&#62;',
					'whole' => 'title="&#60;test&#62;"',
					'vless' => 'n',
				),
			),
		);

		yield 'numeric hex entities lowercase' => array(
			'title="&#x3C;hex&#x3E;"',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => '&#x3C;hex&#x3E;',
					'whole' => 'title="&#x3C;hex&#x3E;"',
					'vless' => 'n',
				),
			),
		);

		yield 'numeric hex entities uppercase' => array(
			'title="&#X3C;HEX&#X3E;"',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => '&#X3C;HEX&#X3E;',
					'whole' => 'title="&#X3C;HEX&#X3E;"',
					'vless' => 'n',
				),
			),
		);

		yield 'invalid character references' => array(
			'title="&invalid; &#; &#x;"',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => '&invalid; &#; &#x;',
					'whole' => 'title="&invalid; &#; &#x;"',
					'vless' => 'n',
				),
			),
		);

		// Quote Handling.
		yield 'double quotes' => array(
			'data-text="Double quoted value"',
			array(
				'data-text' => array(
					'name'  => 'data-text',
					'value' => 'Double quoted value',
					'whole' => 'data-text="Double quoted value"',
					'vless' => 'n',
				),
			),
		);

		yield 'single quotes' => array(
			"data-text='Single quoted value'",
			array(
				'data-text' => array(
					'name'  => 'data-text',
					'value' => 'Single quoted value',
					'whole' => "data-text='Single quoted value'",
					'vless' => 'n',
				),
			),
		);

		yield 'mixed quotes' => array(
			'title="double" alt=\'single\' id=unquoted',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => 'double',
					'whole' => 'title="double"',
					'vless' => 'n',
				),
				'alt'   => array(
					'name'  => 'alt',
					'value' => 'single',
					'whole' => "alt='single'",
					'vless' => 'n',
				),
				'id'    => array(
					'name'  => 'id',
					'value' => 'unquoted',
					'whole' => 'id="unquoted"',
					'vless' => 'n',
				),
			),
		);

		yield 'single quotes in double quoted value' => array(
			'title="It\'s working"',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => "It's working",
					'whole' => 'title="It\'s working"',
					'vless' => 'n',
				),
			),
		);

		yield 'double quotes in single quoted value' => array(
			'title=\'He said "hello"\'',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => 'He said "hello"',
					'whole' => 'title=\'He said "hello"\'',
					'vless' => 'n',
				),
			),
		);

		yield 'unquoted with special chars' => array(
			'data-value=test-123_value',
			array(
				'data-value' => array(
					'name'  => 'data-value',
					'value' => 'test-123_value',
					'whole' => 'data-value="test-123_value"',
					'vless' => 'n',
				),
			),
		);

		// Edge Cases.
		yield 'empty string' => array(
			'',
			array(),
		);

		yield 'whitespace only' => array(
			'   	  ',
			array(),
		);

		yield 'invalid attribute name starting with number' => array(
			'1invalid="value"',
			array(),
		);

		yield 'invalid attribute name special chars' => array(
			'@invalid="value" $bad="value"',
			array(),
		);

		yield 'duplicate attributes first wins' => array(
			'id="first" class="test" id="second"',
			array(
				'id'    => array(
					'name'  => 'id',
					'value' => 'first',
					'whole' => 'id="first"',
					'vless' => 'n',
				),
				'class' => array(
					'name'  => 'class',
					'value' => 'test',
					'whole' => 'class="test"',
					'vless' => 'n',
				),
			),
		);

		yield 'malformed unclosed double quote' => array(
			'title="unclosed class="test"',
			array(),
		);

		yield 'very long attribute value' => array(
			'data-long="' . str_repeat( 'a', 10000 ) . '"',
			array(
				'data-long' => array(
					'name'  => 'data-long',
					'value' => str_repeat( 'a', 10000 ),
					'whole' => 'data-long="' . str_repeat( 'a', 10000 ) . '"',
					'vless' => 'n',
				),
			),
		);

		yield 'attribute names with colons and dots' => array(
			'xml:lang="en" data.value="test" xlink:href="#anchor"',
			array(
				'xml:lang'   => array(
					'name'  => 'xml:lang',
					'value' => 'en',
					'whole' => 'xml:lang="en"',
					'vless' => 'n',
				),
				'data.value' => array(
					'name'  => 'data.value',
					'value' => 'test',
					'whole' => 'data.value="test"',
					'vless' => 'n',
				),
				'xlink:href' => array(
					'name'  => 'xlink:href',
					'value' => '#anchor',
					'whole' => 'xlink:href="#anchor"',
					'vless' => 'n',
				),
			),
		);

		yield 'multiple spaces between attributes' => array(
			'class="test"    id="value"		title="spaced"',
			array(
				'class' => array(
					'name'  => 'class',
					'value' => 'test',
					'whole' => 'class="test"',
					'vless' => 'n',
				),
				'id'    => array(
					'name'  => 'id',
					'value' => 'value',
					'whole' => 'id="value"',
					'vless' => 'n',
				),
				'title' => array(
					'name'  => 'title',
					'value' => 'spaced',
					'whole' => 'title="spaced"',
					'vless' => 'n',
				),
			),
		);

		yield 'spaces around equals' => array(
			'id = "spaced" class ="left" title= "right"',
			array(
				'id'    => array(
					'name'  => 'id',
					'value' => 'spaced',
					'whole' => 'id="spaced"',
					'vless' => 'n',
				),
				'class' => array(
					'name'  => 'class',
					'value' => 'left',
					'whole' => 'class="left"',
					'vless' => 'n',
				),
				'title' => array(
					'name'  => 'title',
					'value' => 'right',
					'whole' => 'title="right"',
					'vless' => 'n',
				),
			),
		);

		// WordPress-specific Cases.
		yield 'common wordpress attributes' => array(
			'class="wp-block" id="post-123" style="color: red;"',
			array(
				'class' => array(
					'name'  => 'class',
					'value' => 'wp-block',
					'whole' => 'class="wp-block"',
					'vless' => 'n',
				),
				'id'    => array(
					'name'  => 'id',
					'value' => 'post-123',
					'whole' => 'id="post-123"',
					'vless' => 'n',
				),
				'style' => array(
					'name'  => 'style',
					'value' => 'color: red;',
					'whole' => 'style="color: red;"',
					'vless' => 'n',
				),
			),
		);

		yield 'data attributes' => array(
			'data-post-id="123" data-action="delete" data-confirm="true"',
			array(
				'data-post-id' => array(
					'name'  => 'data-post-id',
					'value' => '123',
					'whole' => 'data-post-id="123"',
					'vless' => 'n',
				),
				'data-action'  => array(
					'name'  => 'data-action',
					'value' => 'delete',
					'whole' => 'data-action="delete"',
					'vless' => 'n',
				),
				'data-confirm' => array(
					'name'  => 'data-confirm',
					'value' => 'true',
					'whole' => 'data-confirm="true"',
					'vless' => 'n',
				),
			),
		);

		yield 'aria attributes' => array(
			'aria-label="Close" aria-hidden="true" aria-describedby="help-text"',
			array(
				'aria-label'       => array(
					'name'  => 'aria-label',
					'value' => 'Close',
					'whole' => 'aria-label="Close"',
					'vless' => 'n',
				),
				'aria-hidden'      => array(
					'name'  => 'aria-hidden',
					'value' => 'true',
					'whole' => 'aria-hidden="true"',
					'vless' => 'n',
				),
				'aria-describedby' => array(
					'name'  => 'aria-describedby',
					'value' => 'help-text',
					'whole' => 'aria-describedby="help-text"',
					'vless' => 'n',
				),
			),
		);

		yield 'role attribute' => array(
			'role="navigation"',
			array(
				'role' => array(
					'name'  => 'role',
					'value' => 'navigation',
					'whole' => 'role="navigation"',
					'vless' => 'n',
				),
			),
		);

		yield 'tabindex attribute' => array(
			'tabindex="0"',
			array(
				'tabindex' => array(
					'name'  => 'tabindex',
					'value' => '0',
					'whole' => 'tabindex="0"',
					'vless' => 'n',
				),
			),
		);

		yield 'complex wordpress attributes' => array(
			'class="wp-block-button__link" href="https://wordpress.org" target="_blank" rel="noopener" aria-label="Visit WordPress" data-track="click"',
			array(
				'class'      => array(
					'name'  => 'class',
					'value' => 'wp-block-button__link',
					'whole' => 'class="wp-block-button__link"',
					'vless' => 'n',
				),
				'href'       => array(
					'name'  => 'href',
					'value' => 'https://wordpress.org',
					'whole' => 'href="https://wordpress.org"',
					'vless' => 'n',
				),
				'target'     => array(
					'name'  => 'target',
					'value' => '_blank',
					'whole' => 'target="_blank"',
					'vless' => 'n',
				),
				'rel'        => array(
					'name'  => 'rel',
					'value' => 'noopener',
					'whole' => 'rel="noopener"',
					'vless' => 'n',
				),
				'aria-label' => array(
					'name'  => 'aria-label',
					'value' => 'Visit WordPress',
					'whole' => 'aria-label="Visit WordPress"',
					'vless' => 'n',
				),
				'data-track' => array(
					'name'  => 'data-track',
					'value' => 'click',
					'whole' => 'data-track="click"',
					'vless' => 'n',
				),
			),
		);

		yield 'underscore in attribute name' => array(
			'_custom="value" data_value="test"',
			array(
				'_custom'    => array(
					'name'  => '_custom',
					'value' => 'value',
					'whole' => '_custom="value"',
					'vless' => 'n',
				),
				'data_value' => array(
					'name'  => 'data_value',
					'value' => 'test',
					'whole' => 'data_value="test"',
					'vless' => 'n',
				),
			),
		);

		yield 'empty attribute value' => array(
			'title="" alt=\'\' class=""',
			array(
				'title' => array(
					'name'  => 'title',
					'value' => '',
					'whole' => 'title=""',
					'vless' => 'n',
				),
				'alt'   => array(
					'name'  => 'alt',
					'value' => '',
					'whole' => "alt=''",
					'vless' => 'n',
				),
				'class' => array(
					'name'  => 'class',
					'value' => '',
					'whole' => 'class=""',
					'vless' => 'n',
				),
			),
		);

		// Whitespace Handling.
		yield 'forward slashes between attributes' => array(
			'att="val" / att-2="val-2" / / ///// att-3="val-3"',
			array(
				'att'   => array(
					'name'  => 'att',
					'value' => 'val',
					'whole' => 'att="val"',
					'vless' => 'n',
				),
				'att-2' => array(
					'name'  => 'att-2',
					'value' => 'val-2',
					'whole' => 'att-2="val-2"',
					'vless' => 'n',
				),
				'att-3' => array(
					'name'  => 'att-3',
					'value' => 'val-3',
					'whole' => 'att-3="val-3"',
					'vless' => 'n',
				),
			),
		);

		yield 'tab whitespace' => array(
			"att=\"val\"\tatt2=\"val2\"",
			array(
				'att'  => array(
					'name'  => 'att',
					'value' => 'val',
					'whole' => 'att="val"',
					'vless' => 'n',
				),
				'att2' => array(
					'name'  => 'att2',
					'value' => 'val2',
					'whole' => 'att2="val2"',
					'vless' => 'n',
				),
			),
		);

		yield 'form feed whitespace' => array(
			"att=\"val\"\fatt2=\"val2\"",
			array(
				'att'  => array(
					'name'  => 'att',
					'value' => 'val',
					'whole' => 'att="val"',
					'vless' => 'n',
				),
				'att2' => array(
					'name'  => 'att2',
					'value' => 'val2',
					'whole' => 'att2="val2"',
					'vless' => 'n',
				),
			),
		);

		yield 'carriage return whitespace' => array(
			"att=\"val\"\ratt2=\"val2\"",
			array(
				'att'  => array(
					'name'  => 'att',
					'value' => 'val',
					'whole' => 'att="val"',
					'vless' => 'n',
				),
				'att2' => array(
					'name'  => 'att2',
					'value' => 'val2',
					'whole' => 'att2="val2"',
					'vless' => 'n',
				),
			),
		);

		yield 'newline whitespace' => array(
			"att=\"val\"\natt2=\"val2\"",
			array(
				'att'  => array(
					'name'  => 'att',
					'value' => 'val',
					'whole' => 'att="val"',
					'vless' => 'n',
				),
				'att2' => array(
					'name'  => 'att2',
					'value' => 'val2',
					'whole' => 'att2="val2"',
					'vless' => 'n',
				),
			),
		);

		yield 'mixed whitespace types' => array(
			"att=\"val\"\t\r\n\f att2=\"val2\"",
			array(
				'att'  => array(
					'name'  => 'att',
					'value' => 'val',
					'whole' => 'att="val"',
					'vless' => 'n',
				),
				'att2' => array(
					'name'  => 'att2',
					'value' => 'val2',
					'whole' => 'att2="val2"',
					'vless' => 'n',
				),
			),
		);

		// Malformed Equals Patterns.
		yield 'multiple equals signs' => array(
			'att=="val"',
			array(),
		);

		yield 'equals with strange spacing' => array(
			'att= ="val"',
			array(),
		);

		yield 'triple equals signs' => array(
			'att==="val"',
			array(),
		);

		yield 'equals echo pattern' => array(
			"att==echo 'something'",
			array(
				'att' => array(
					'name'  => 'att',
					'value' => '=echo',
					'whole' => 'att="=echo"',
					'vless' => 'n',
				),
			),
		);

		yield 'attribute starting with equals' => array(
			'= bool k=v',
			array(
				'bool' => array(
					'name'  => 'bool',
					'value' => '',
					'whole' => 'bool',
					'vless' => 'y',
				),
				'k'    => array(
					'name'  => 'k',
					'value' => 'v',
					'whole' => 'k="v"',
					'vless' => 'n',
				),
			),
		);

		yield 'mixed quotes and equals chaos' => array(
			'k=v ="' . "' j=w",
			array(
				'k' => array(
					'name'  => 'k',
					'value' => 'v',
					'whole' => 'k="v"',
					'vless' => 'n',
				),
			),
		);

		yield 'triple equals quoted whitespace' => array(
			'==="  "',
			array(),
		);

		yield 'boolean with contradictory value' => array(
			'disabled=enabled checked',
			array(
				'disabled' => array(
					'name'  => 'disabled',
					'value' => 'enabled',
					'whole' => 'disabled="enabled"',
					'vless' => 'n',
				),
				'checked'  => array(
					'name'  => 'checked',
					'value' => '',
					'whole' => 'checked',
					'vless' => 'y',
				),
			),
		);

		yield 'empty attribute name with value' => array(
			'="value" class="test"',
			array(
				'class' => array(
					'name'  => 'class',
					'value' => 'test',
					'whole' => 'class="test"',
					'vless' => 'n',
				),
			),
		);
	}

	/**
	 * Test wp_kses_hair() with URL protocol filtering.
	 *
	 * @ticket 63724
	 * @dataProvider data_protocol_filtering
	 * @covers wp_kses_hair
	 */
	public function test_protocol_filtering( string $input, array $expected ) {
		$result = wp_kses_hair( $input, $this->allowed_protocols );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for URL protocol filtering tests.
	 *
	 * @return Generator
	 */
	public function data_protocol_filtering() {
		yield 'href allowed protocol http' => array(
			'href="http://example.com"',
			array(
				'href' => array(
					'name'  => 'href',
					'value' => 'http://example.com',
					'whole' => 'href="http://example.com"',
					'vless' => 'n',
				),
			),
		);

		yield 'href allowed protocol https' => array(
			'href="https://secure.example.com"',
			array(
				'href' => array(
					'name'  => 'href',
					'value' => 'https://secure.example.com',
					'whole' => 'href="https://secure.example.com"',
					'vless' => 'n',
				),
			),
		);

		yield 'href disallowed protocol javascript' => array(
			'href="javascript:alert(1)"',
			array(
				'href' => array(
					'name'  => 'href',
					'value' => 'alert(1)',
					'whole' => 'href="alert(1)"',
					'vless' => 'n',
				),
			),
		);

		yield 'href disallowed protocol javascript single quotes' => array(
			"href='javascript:alert(1)'",
			array(
				'href' => array(
					'name'  => 'href',
					'value' => 'alert(1)',
					'whole' => "href='alert(1)'",
					'vless' => 'n',
				),
			),
		);

		yield 'href disallowed protocol javascript unquoted' => array(
			'href=javascript:alert(1)',
			array(
				'href' => array(
					'name'  => 'href',
					'value' => 'alert(1)',
					'whole' => 'href="alert(1)"',
					'vless' => 'n',
				),
			),
		);

		yield 'src allowed protocol' => array(
			'src="https://example.com/image.jpg"',
			array(
				'src' => array(
					'name'  => 'src',
					'value' => 'https://example.com/image.jpg',
					'whole' => 'src="https://example.com/image.jpg"',
					'vless' => 'n',
				),
			),
		);

		yield 'src data protocol' => array(
			'src="data:text/html,<script>alert(1)</script>"',
			array(
				'src' => array(
					'name'  => 'src',
					'value' => 'text/html,<script>alert(1)</script>',
					'whole' => 'src="text/html,<script>alert(1)</script>"',
					'vless' => 'n',
				),
			),
		);

		yield 'protocol filtering only uri attributes' => array(
			'data-url="javascript:alert(1)"',
			array(
				'data-url' => array(
					'name'  => 'data-url',
					'value' => 'javascript:alert(1)',
					'whole' => 'data-url="javascript:alert(1)"',
					'vless' => 'n',
				),
			),
		);

		yield 'href relative url' => array(
			'href="/path/to/page"',
			array(
				'href' => array(
					'name'  => 'href',
					'value' => '/path/to/page',
					'whole' => 'href="/path/to/page"',
					'vless' => 'n',
				),
			),
		);

		yield 'href anchor link' => array(
			'href="#section"',
			array(
				'href' => array(
					'name'  => 'href',
					'value' => '#section',
					'whole' => 'href="#section"',
					'vless' => 'n',
				),
			),
		);
	}

	/**
	 * Test wp_kses_hair() with custom allowed protocols.
	 *
	 * @ticket 63724
	 * @covers wp_kses_hair
	 */
	public function test_custom_allowed_protocols() {
		$custom_protocols = array( 'gopher' );
		$attr             = 'href="gopher://gopher.example.org"';
		$result           = wp_kses_hair( $attr, $custom_protocols );

		$expected = array(
			'href' => array(
				'name'  => 'href',
				'value' => 'gopher://gopher.example.org',
				'whole' => 'href="gopher://gopher.example.org"',
				'vless' => 'n',
			),
		);

		$this->assertSame( $expected, $result );
	}
}
