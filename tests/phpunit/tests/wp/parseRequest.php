<?php

/**
 * @group wp
 * @covers WP::parse_request
 */
class Tests_WP_ParseRequest extends WP_UnitTestCase {
	/**
	 * @var WP
	 */
	protected $wp;

	public function set_up() {
		parent::set_up();
		$this->wp = new WP();
	}

	/**
	 * Tests the return value of the parse_request() method.
	 *
	 * @ticket 10886
	 */
	public function test_parse_request_returns_bool() {
		// Check that parse_request() returns true by default.
		$this->assertTrue( $this->wp->parse_request() );

		add_filter( 'do_parse_request', '__return_false' );

		// Check that parse_request() returns false if the request was not parsed.
		$this->assertFalse( $this->wp->parse_request() );
	}

	/**
	 * Tests that PHP 8.1 "passing null to non-nullable" deprecation notice
	 * is not thrown when the home URL has no path/trailing slash (default setup).
	 *
	 * Note: This does not test the actual functioning of the parse_request() method.
	 * It just and only tests for/against the deprecation notice.
	 *
	 * @ticket 53635
	 */
	public function test_no_deprecation_notice_when_home_url_has_no_path() {
		// Make sure rewrite rules are not empty.
		$this->set_permalink_structure( '/%year%/%monthnum%/%postname%/' );

		// Make sure the test will function independently of whatever the test user set in wp-tests-config.php.
		add_filter(
			'home_url',
			static function ( $url ) {
				return 'http://example.org';
			}
		);

		$this->wp->parse_request();
		$this->assertSame( '', $this->wp->request );
	}
}
