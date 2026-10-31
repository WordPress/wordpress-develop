<?php

/**
 * Tests for tag_escape().
 *
 * @group formatting
 *
 * @covers ::tag_escape
 */
class Tests_Formatting_TagEscape extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_tag_escape
	 *
	 * @param string $tag_name Tag name input.
	 * @param string $expected Expected escaped tag name.
	 */
	public function test_tag_escape( $tag_name, $expected ) {
		$this->assertSame( $expected, tag_escape( $tag_name ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_tag_escape() {
		return array(
			'lowercase tag'               => array( 'div', 'div' ),
			'uppercase lowercased'        => array( 'DIV', 'div' ),
			'mixed case'                  => array( 'SpAn', 'span' ),
			'allows hyphen underscore'    => array( 'my-custom_tag', 'my-custom_tag' ),
			'allows colon for namespaced' => array( 'svg:path', 'svg:path' ),
			'invalid characters removed'  => array( 'div<script>', 'divscript' ),
			'invalid spaces not allowed'  => array( 'hello world', 'helloworld' ),
		);
	}

	/**
	 * @ticket 65819
	 */
	public function test_tag_escape_filter() {
		add_filter(
			'tag_escape',
			static function ( $safe_tag ) {
				return 'filtered-' . $safe_tag;
			}
		);

		$this->assertSame( 'filtered-div', tag_escape( 'DIV' ) );
	}
}
