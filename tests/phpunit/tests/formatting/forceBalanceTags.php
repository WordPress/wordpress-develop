<?php
/**
 * Tests for force_balance_tags().
 *
 * Related coverage for the balanceTags() wrapper with $force = true lives in
 * Tests_Formatting_BalanceTags. These tests call force_balance_tags() directly.
 *
 * @group formatting
 *
 * @covers ::force_balance_tags
 */
class Tests_Formatting_ForceBalanceTags extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_force_balance_tags
	 *
	 * @param string $input    Input markup.
	 * @param string $expected Expected balanced markup.
	 */
	public function test_force_balance_tags( $input, $expected ) {
		$this->assertSame( $expected, force_balance_tags( $input ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_force_balance_tags() {
		return array(
			'empty string'                         => array(
				'',
				'',
			),
			'plain text unchanged'                 => array(
				'Hello world',
				'Hello world',
			),
			'already balanced markup unchanged'    => array(
				'<p>Hello <strong>world</strong></p>',
				'<p>Hello <strong>world</strong></p>',
			),
			'closes unclosed paragraph'            => array(
				'<p>Test',
				'<p>Test</p>',
			),
			'closes nested unclosed tags'          => array(
				'<b><i>Test</b>',
				'<b><i>Test</i></b>',
			),
			'removes extraneous closing tags'      => array(
				'<b>Test</b></b>',
				'<b>Test</b>',
			),
			'fixes improper closing tag sequence'  => array(
				'<p>Here is a <strong class="part">bold <em>and emphasis</p></em></strong>',
				'<p>Here is a <strong class="part">bold <em>and emphasis</em></strong></p>',
			),
			'self-closes known single tags'        => array(
				'<br>',
				'<br />',
			),
			'self-closes single tags with attrs'   => array(
				'<img src="/images/example.png">',
				'<img src="/images/example.png"/>',
			),
			'keeps valid self-closing single tags' => array(
				'<br />',
				'<br />',
			),
			'closes unknown self-closing tags'     => array(
				'<strong/>',
				'<strong></strong>',
			),
			'allows nestable adjacent tags'        => array(
				'<div><div>Allowed</div></div>',
				'<div><div>Allowed</div></div>',
			),
			'closes incomplete nestable tags'      => array(
				'<div><div>Test',
				'<div><div>Test</div></div>',
			),
			'rewrites nested non-nestable tags'    => array(
				'<b><b>This is bold</b></b>',
				'<b></b><b>This is bold</b>',
			),
			'escapes less-than before a digit'     => array(
				'I <3 WordPress',
				'I &lt;3 WordPress',
			),
			'closes custom elements'               => array(
				'<my-custom-element>Test',
				'<my-custom-element>Test</my-custom-element>',
			),
			'ignores uppercase custom elements'    => array(
				'<MY-CUSTOM-ELEMENT>Test',
				'<MY-CUSTOM-ELEMENT>Test',
			),
			'preserves html comments'              => array(
				'<!-- comment --><p>Hi',
				'<!-- comment --><p>Hi</p>',
			),
			'normalizes spaced comment opener'     => array(
				'< !-- comment --><p>Hi',
				'< !-- comment --><p>Hi</p>',
			),
		);
	}
}
