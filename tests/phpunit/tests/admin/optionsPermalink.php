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
		return [
			[ 'Foo Bar', '/foo-bar' ],
			[ 'Foo & Bar!', '/foo-bar' ],
			[ 'Foo Bar/Baz Qux', '/foo-bar/baz-qux' ],
			[ '', '' ],
			[ '/Foo Bar', '/foo-bar' ],
			[ 'Multiple/Slashes', '/multiple/slashes' ],
		];
	}
}