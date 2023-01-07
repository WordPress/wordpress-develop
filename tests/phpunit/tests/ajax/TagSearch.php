<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing Ajax tag search functionality.
 *
 * @package    WordPress
 * @subpackage UnitTests
 * @since      3.4.0
 * @group      ajax
 *
 * @covers ::wp_ajax_ajax_tag_search
 */
class Tests_Ajax_TagSearch extends WP_Ajax_UnitTestCase {

	/**
	 * List of terms to insert on setup
	 *
	 * @var array
	 */
	private static $terms = array(
		'chattels',
		'depo',
		'energumen',
		'figuriste',
		'habergeon',
		'impropriation',
	);

	private static $term_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		foreach ( self::$terms as $t ) {
			self::$term_ids[] = wp_insert_term( $t, 'post_tag' );
		}
	}

	/**
	 * Test as an admin
	 */
	public function test_post_tag() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['tax'] = 'post_tag';
		$_GET['q']   = 'chat';

		// Make the request.
		try {
			$this->_handleAjax( 'ajax-tag-search' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Ensure we found the right match.
		$this->assertSame( $this->_last_response, 'chattels' );
	}

	/**
	 * Test with no results
	 */
	public function test_no_results() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['tax'] = 'post_tag';
		$_GET['q']   = md5( uniqid() );

		// Make the request.
		// No output, so we get a stop exception.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '' );
		$this->_handleAjax( 'ajax-tag-search' );
	}

	/**
	 * Test with commas
	 */
	public function test_with_comma() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['tax'] = 'post_tag';
		$_GET['q']   = 'some,nonsense, terms,chat'; // Only the last term in the list is searched.

		// Make the request.
		try {
			$this->_handleAjax( 'ajax-tag-search' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Ensure we found the right match.
		$this->assertSame( $this->_last_response, 'chattels' );
	}

	/**
	 * Test as a logged out user
	 */
	public function test_logged_out() {

		// Log out.
		wp_logout();

		// Set up a default request.
		$_GET['tax'] = 'post_tag';
		$_GET['q']   = 'chat';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'ajax-tag-search' );
	}

	/**
	 * Test with an invalid taxonomy type
	 */
	public function test_invalid_tax() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['tax'] = 'invalid-taxonomy';
		$_GET['q']   = 'chat';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '0' );
		$this->_handleAjax( 'ajax-tag-search' );
	}

	/**
	 * Test as an unprivileged user
	 */
	public function test_unprivileged_user() {

		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		// Set up a default request.
		$_GET['tax'] = 'post_tag';
		$_GET['q']   = 'chat';

		// Make the request.
		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( 'ajax-tag-search' );
	}

	/**
	 * Test the ajax_term_search_results filter
	 *
	 * @ticket 55606
	 */
	public function test_ajax_term_search_results_filter() {

		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up a default request.
		$_GET['tax'] = 'post_tag';
		$_GET['q']   = 'chat';

		// Add the ajax_term_search_results filter.
		add_filter(
			'ajax_term_search_results',
			static function( $results, $tax, $s ) {
				return array( 'ajax_term_search_results was applied' );
			},
			10,
			3
		);

		// Make the request.
		try {
			$this->_handleAjax( 'ajax-tag-search', $_GET['tax'], $_GET['q'] );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}

		// Ensure we found the right match.
		$this->assertSame( 'ajax_term_search_results was applied', $this->_last_response );
	}
}
