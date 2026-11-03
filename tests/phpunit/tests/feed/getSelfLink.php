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
	 * @ticket 65819
	 */
	public function test_builds_scheme_aware_url_from_request_uri() {
		$_SERVER['REQUEST_URI'] = '/feed/rss2/?foo=bar';

		$parsed = parse_url( home_url() );
		$domain = $parsed['host'];
		if ( isset( $parsed['port'] ) ) {
			$domain .= ':' . $parsed['port'];
		}

		$this->assertSame(
			set_url_scheme( 'http://' . $domain . '/feed/rss2/?foo=bar' ),
			get_self_link()
		);
	}
}
