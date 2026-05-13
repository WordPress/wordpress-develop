<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_fetch_list() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_fetch_list
 */
class Tests_Ajax_wpAjaxFetchList extends WP_Ajax_UnitTestCase {

	/**
	 * Tests fetching a list table via AJAX.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list(): void {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up the $_GET request.
		$list_class = 'WP_Posts_List_Table';

		$_GET = array(
			'list_args'  => array(
				'class'  => $list_class,
				'screen' => array(
					'id' => 'edit-post',
				),
			),
			'_ajax_fetch_list_nonce' => wp_create_nonce( "fetch-list-$list_class" ),
		);

		// Make the request.
		try {
			$this->_handleAjax( 'fetch-list' );
		} catch ( WPAjaxDieContinueException $e ) {
			// Expected exception.
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			// Expected exception.
			unset( $e );
		} catch ( Exception $e ) {
			$this->fail( 'Unexpected exception: ' . $e->getMessage() );
		}

		if ( empty( $this->_last_response ) ) {
			$this->fail( 'Ajax response was empty' );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'rows', $response );
		$this->assertStringContainsString( 'No posts found.', $response['rows'] );
	}

	/**
	 * Tests fetching a list table with items.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list_with_items(): void {
		$this->_setRole( 'administrator' );

		// Create a post.
		self::factory()->post->create( array( 'post_title' => 'Test Post' ) );

		$list_class = 'WP_Posts_List_Table';

		$_GET = array(
			'list_args'  => array(
				'class'  => $list_class,
				'screen' => array(
					'id' => 'edit-post',
				),
			),
			'_ajax_fetch_list_nonce' => wp_create_nonce( "fetch-list-$list_class" ),
		);

		try {
			$this->_handleAjax( 'fetch-list' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		$response = json_decode( $this->_last_response, true );

		$this->assertIsArray( $response );
		$this->assertArrayHasKey( 'rows', $response );
		$this->assertStringContainsString( 'Test Post', $response['rows'] );
		$this->assertSame( '1 item', $response['total_items_i18n'] );
	}

	/**
	 * Tests fetching a list table with an invalid nonce.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list_invalid_nonce(): void {
		$this->_setRole( 'administrator' );

		$list_class = 'WP_Posts_List_Table';

		$_GET = array(
			'list_args' => array(
				'class' => $list_class,
			),
			'_ajax_fetch_list_nonce' => 'invalid-nonce',
		);

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'fetch-list' );
	}

	/**
	 * Tests fetching a list table with an invalid class.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list_invalid_class(): void {
		$this->_setRole( 'administrator' );

		$list_class = 'Invalid_List_Table';

		$_GET = array(
			'list_args' => array(
				'class' => $list_class,
			),
			'_ajax_fetch_list_nonce' => wp_create_nonce( "fetch-list-$list_class" ),
		);

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '0' );

		$this->_handleAjax( 'fetch-list' );
	}

	/**
	 * Tests fetching a list table as an unprivileged user.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list_unprivileged_user(): void {
		// Become a subscriber.
		$this->_setRole( 'subscriber' );

		$list_class = 'WP_Posts_List_Table';

		$_GET = array(
			'list_args'  => array(
				'class'  => $list_class,
				'screen' => array(
					'id' => 'edit-post',
				),
			),
			'_ajax_fetch_list_nonce' => wp_create_nonce( "fetch-list-$list_class" ),
		);

		$this->expectException( 'WPAjaxDieStopException' );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'fetch-list' );
	}
}
