<?php

/**
 * Tests for the user_trailingslashit() function.
 *
 * @group link
 * @covers ::user_trailingslashit
 */
class Tests_Link_UserTrailingslashit extends WP_UnitTestCase {

	/**
	 * Tests user_trailingslashit() when trailing slashes are enabled.
	 *
	 * @ticket 65819
	 */
	public function test_user_trailingslashit_when_trailing_slashes_enabled() {
		$this->set_permalink_structure( '/%postname%/' );

		$this->assertSame( 'https://example.org/sample-post/', user_trailingslashit( 'https://example.org/sample-post' ) );
	}

	/**
	 * Tests user_trailingslashit() when trailing slashes are disabled.
	 *
	 * @ticket 65819
	 */
	public function test_user_trailingslashit_when_trailing_slashes_disabled() {
		$this->set_permalink_structure( '/%postname%' );

		$this->assertSame( 'https://example.org/sample-post', user_trailingslashit( 'https://example.org/sample-post/' ) );
	}

	/**
	 * Tests that the 'user_trailingslashit' filter can modify the output.
	 *
	 * @ticket 65819
	 */
	public function test_user_trailingslashit_filter_modifies_url() {
		$this->set_permalink_structure( '/%postname%/' );

		$filter_cb = static function ( $url, $type_of_url ) {
			return $url . '?custom_arg=' . $type_of_url;
		};

		add_filter( 'user_trailingslashit', $filter_cb, 10, 2 );
		$result = user_trailingslashit( 'https://example.org/test', 'custom_type' );
		remove_filter( 'user_trailingslashit', $filter_cb, 10 );

		$this->assertSame( 'https://example.org/test/?custom_arg=custom_type', $result );
	}
}
