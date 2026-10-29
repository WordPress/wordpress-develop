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
	 */
	public function test_returns_sites_when_blog_is_public() {
		add_filter(
			'pre_option_blog_public',
			static function () {
				return '1';
			}
		);

		$sites = "http://example.com\nhttp://example.org";

		$this->assertSame( $sites, privacy_ping_filter( $sites ) );
	}

	/**
	 * @ticket 65819
	 */
	public function test_returns_empty_string_when_blog_is_not_public() {
		add_filter(
			'pre_option_blog_public',
			static function () {
				return '0';
			}
		);

		$this->assertSame( '', privacy_ping_filter( "http://example.com\nhttp://example.org" ) );
	}

	/**
	 * Non-'0' public values keep the sites list (strict comparison).
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_public_blog_values
	 *
	 * @param mixed $blog_public Option value for blog_public.
	 */
	public function test_returns_sites_for_non_zero_public_values( $blog_public ) {
		add_filter(
			'pre_option_blog_public',
			static function () use ( $blog_public ) {
				return $blog_public;
			}
		);

		$this->assertSame( 'http://example.com', privacy_ping_filter( 'http://example.com' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_public_blog_values() {
		return array(
			'string 1'     => array( '1' ),
			'integer 1'    => array( 1 ),
			'empty string' => array( '' ),
		);
	}
}
