<?php

/**
 * @group wp
 *
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

	/**
	 * Tests that a query variable which only ever holds a single value results
	 * in a 404 when it is given an array instead.
	 *
	 * @ticket 60745
	 *
	 * @dataProvider data_single_value_query_vars
	 *
	 * @param string $query_var The name of the single value query variable.
	 */
	public function test_array_value_for_single_value_query_var_results_in_404( string $query_var ) {
		/*
		 * Some of these are registered as public query variables at runtime, for
		 * example 'post_format' is added by register_taxonomy(). Use the list from
		 * the global WP instance so those are covered too.
		 */
		$this->wp->public_query_vars = $GLOBALS['wp']->public_query_vars;

		$this->assertContains( $query_var, $this->wp->public_query_vars, "The '$query_var' query variable should be a public query variable." );

		$_GET[ $query_var ] = array( 'foo', 'bar' );

		$this->wp->parse_request();

		$this->assertArrayNotHasKey( $query_var, $this->wp->query_vars, "The '$query_var' query variable should have been removed." );
		$this->assertSame( 404, $this->wp->query_vars['error'], "An array value for '$query_var' should result in a 404." );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{query_var: string}>
	 */
	public function data_single_value_query_vars(): array {
		return $this->text_array_to_dataprovider(
			array(
				'attachment',
				'author_name',
				'feed',
				'post_format',
			)
		);
	}
}
