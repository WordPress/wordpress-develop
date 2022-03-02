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
	 * Test that PHP 8.1 "passing null to non-nullable" deprecation notice
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
	/**
	 * Test that the parse_request() returns bool
	 *
	 * @ticket 10886
	 */
	public function test_parse_request_returns_bool() {

		// check if parse_request() returns true for default setup.
		$this->assertTrue( $this->wp->parse_request(), 'returns true' );

		// add filter to shortcut the parse_request function.
		add_filter( 'do_parse_request', '__return_false' );
		$this->assertFalse( $this->wp->parse_request(), 'returns false' );
		remove_filter( 'do_parse_request', '__return_false' );

	}
}
