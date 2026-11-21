<?php
/**
 * Tests for get_self_link().
 *
 * @group feed
 *
 * @covers ::get_self_link
 */
class Tests_Feed_GetSelfLink extends WP_UnitTestCase {

	/**
	 * @ticket 66480
	 */
	public function test_builds_scheme_aware_url_from_request_uri() {
		update_option( 'home', 'http://example.org' );

		$_SERVER['HTTPS']       = 'on';
		$_SERVER['REQUEST_URI'] = '/feed/rss2/?foo=bar';

		$this->assertSame(
			'https://example.org/feed/rss2/?foo=bar',
			get_self_link()
		);
	}
}
