<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_wp_link_ajax() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_wp_link_ajax
 */
class Tests_wp_ajax_wp_link_ajax extends WP_Ajax_UnitTestCase {

	/**
	 * Tests successful search using the 'search' parameter.
	 *
	 * @ticket 65252
	 */
	public function test_wp_link_ajax_search_success(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Test Link Search',
				'post_status' => 'publish',
			)
		);

		$_POST = array(
			'action'              => 'wp-link-ajax',
			'_ajax_linking_nonce' => wp_create_nonce( 'internal-linking' ),
			'search'              => 'Test Link Search',
		);

		try {
			$this->_handleAjax( 'wp-link-ajax' );
		} catch ( WPAjaxDieContinueException $e ) {
			// We expect it to die after echoing JSON.
		}

		$response = json_decode( trim( $this->_last_response ), true );

		$this->assertIsArray( $response );
		$this->assertCount( 1, $response );
		$this->assertSame( $post->ID, $response[0]['ID'] );
		$this->assertSame( $post->post_title, $response[0]['title'] );
	}

	/**
	 * Tests successful search using the 'term' parameter.
	 *
	 * @ticket 65252
	 */
	public function test_wp_link_ajax_term_success(): void {
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'  => 'Another Test Link',
				'post_status' => 'publish',
			)
		);

		$_POST = array(
			'action'              => 'wp-link-ajax',
			'_ajax_linking_nonce' => wp_create_nonce( 'internal-linking' ),
			'term'                => 'Another Test Link',
		);

		try {
			$this->_handleAjax( 'wp-link-ajax' );
		} catch ( WPAjaxDieContinueException $e ) {
			// We expect it to die after echoing JSON.
		}

		$response = json_decode( trim( $this->_last_response ), true );

		$this->assertIsArray( $response );
		$this->assertCount( 1, $response );
		$this->assertSame( $post->ID, $response[0]['ID'] );
	}

	/**
	 * Tests successful search with pagination.
	 *
	 * @ticket 65252
	 */
	public function test_wp_link_ajax_pagination(): void {
		// Create 21 posts to ensure at least 2 pages (default is 20 per page).
		self::factory()->post->create_many(
			21,
			array(
				'post_title'  => 'Paginated Post',
				'post_status' => 'publish',
			)
		);

		$_POST = array(
			'action'              => 'wp-link-ajax',
			'_ajax_linking_nonce' => wp_create_nonce( 'internal-linking' ),
			'search'              => 'Paginated Post',
			'page'                => 2,
		);

		try {
			$this->_handleAjax( 'wp-link-ajax' );
		} catch ( WPAjaxDieContinueException $e ) {
			// We expect it to die after echoing JSON.
		}

		$response = json_decode( trim( $this->_last_response ), true );

		$this->assertIsArray( $response );
		// On page 2, we should have 1 post.
		$this->assertCount( 1, $response );
	}

	/**
	 * Tests search failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_wp_link_ajax_invalid_nonce(): void {
		$_POST = array(
			'action'              => 'wp-link-ajax',
			'_ajax_linking_nonce' => 'invalid-nonce',
			'search'              => 'Test',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'wp-link-ajax' );
	}

	/**
	 * Tests behavior when no results are found.
	 *
	 * @ticket 65252
	 */
	public function test_wp_link_ajax_no_results(): void {
		$_POST = array(
			'action'              => 'wp-link-ajax',
			'_ajax_linking_nonce' => wp_create_nonce( 'internal-linking' ),
			'search'              => 'NonExistentPostTitle',
		);

		try {
			$this->_handleAjax( 'wp-link-ajax' );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '', $e->getMessage() );
			return;
		}

		$this->fail( 'Expected WPAjaxDieContinueException was not thrown.' );
	}
}
