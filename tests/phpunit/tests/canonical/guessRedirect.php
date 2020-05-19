<?php

/**
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical_GuessRedirect extends WP_Canonical_UnitTestCase {

	// These test cases are run against the test handler in WP_Canonical.
	public function setUp() {
		parent::setUp();
	}

	/**
	 * @dataProvider data_guess_redirect
	 */
	function test_guess_redirect( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {
		add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
		$this->assertCanonical( $test_url, $expected, $ticket, $expected_doing_it_wrong );
		remove_filter( 'do_redirect_guess_404_permalink', '__return_false' );
	}

	/**
	 * @dataProvider data_guess_redirect
	 */
	function test_strict_guess_redirect( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {
		add_filter( 'strict_redirect_guess_404_permalink', '__return_true' );
		$this->assertCanonical( $test_url, $expected, $ticket, $expected_doing_it_wrong );
		remove_filter( 'strict_redirect_guess_404_permalink', '__return_true' );
	}

	function data_guess_redirect() {
		/*
		 * Test URL.
		 * [0]: Test URL.
		 * [1]: Expected results: Any of the following can be used.
		 *      array( 'url': expected redirection location, 'qv': expected query vars to be set via the rewrite AND $_GET );
		 *      array( expected query vars to be set, same as 'qv' above );
		 *      (string) expected redirect location.
		 * [3]: (optional) The ticket the test refers to. Can be skipped if unknown.
		 */
		return array(
			array( '/2008/06/02/post-format-test-au/', '/2008/06/02/post-format-test-au/' ),
			array( '/2008/06/02/post-format-test-audio/', '/2008/06/02/post-format-test-audio/' ),
			array( '?p=587', '/2008/06/02/post-format-test-audio/' ),
			array( '/2008/09/03/images-t/', '/2008/09/03/images-t/' ),
			array( '/2008/09/03/images-test/', '/2008/09/03/images-test/' ),
			array( '/?page_id=144', '/parent-page/child-page-1/' ),
		);
	}
}
