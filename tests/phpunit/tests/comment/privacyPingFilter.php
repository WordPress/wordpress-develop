<?php
/**
 * Tests for privacy_ping_filter().
 *
 * @group comment
 *
 * @covers ::privacy_ping_filter
 */
class Tests_Comment_PrivacyPingFilter extends WP_UnitTestCase {

	/**
	 * @ticket 65819
	 *
	 * @dataProvider data_privacy_ping_filter
	 *
	 * @param mixed  $blog_public Option value for blog_public.
	 * @param string $expected    Expected return value.
	 */
	public function test_privacy_ping_filter( $blog_public, $expected ) {
		add_filter(
			'pre_option_blog_public',
			static function () use ( $blog_public ) {
				return $blog_public;
			}
		);

		$sites = 'http://example.com';

		$this->assertSame( $expected, privacy_ping_filter( $sites ) );
	}

	/**
	 * Data provider.
	 *
	 * Integer `0` is intentionally omitted. `privacy_ping_filter()` compares
	 * strictly with string `'0'`, so integer `0` currently keeps the sites list.
	 * That looks incorrect for a private site and should not be locked in here.
	 *
	 * @return array[]
	 */
	public static function data_privacy_ping_filter() {
		return array(
			'public, string 1'  => array( '1', 'http://example.com' ),
			'public, integer 1' => array( 1, 'http://example.com' ),
			'private, string 0' => array( '0', '' ),
			'empty string'      => array( '', 'http://example.com' ),
		);
	}
}
