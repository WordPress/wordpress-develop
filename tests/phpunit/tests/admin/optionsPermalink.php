<?php

/**
 * Tests for wp-admin/options-permalink.php
 *
 * @group admin
 * @group rewrite
 */
class Tests_Admin_OptionsPermalink extends WP_UnitTestCase {
	/**
	 * Data provider for base sanitization tests.
	 */
	public function data_base_sanitization() {
		return array(
			array( 'Foo Bar', '/foo-bar' ),
			array( 'Foo & Bar!', '/foo-bar' ),
			array( 'Foo Bar/Baz Qux', '/foo-bar/baz-qux' ),
			array( '', '' ),
			array( '/Foo Bar', '/foo-bar' ),
			array( 'Multiple/Slashes', '/multiple/slashes' ),
		);
	}

	/**
	 * Test category and tag base sanitization.
	 *
	 * @ticket 16839
	 * @dataProvider data_base_sanitization
	 */
	public function test_base_sanitization( $input, $expected ) {
		$base   = ltrim( $input, '/' );
		$result = empty( $base ) ? '' : '/' . implode( '/', array_map( 'sanitize_title_with_dashes', preg_split( '|/+|', $base ) ) );

		$this->assertSame( $expected, $result );
	}
}
