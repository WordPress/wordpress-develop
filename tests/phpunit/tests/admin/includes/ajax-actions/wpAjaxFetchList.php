<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';

/**
 * Test list table class with an interceptable response implementation.
 */
class Test_WP_Posts_List_Table extends WP_Posts_List_Table {

	/**
	 * Handles an incoming ajax request and sends JSON via wp_send_json()
	 * so it can be intercepted by WP_Ajax_UnitTestCase.
	 */
	public function ajax_response() {
		$this->prepare_items();

		ob_start();
		if ( ! empty( $_REQUEST['no_placeholder'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->display_rows();
		} else {
			$this->display_rows_or_placeholder();
		}

		$rows = ob_get_clean();

		$response = array( 'rows' => $rows );

		if ( isset( $this->_pagination_args['total_items'] ) ) {
			$response['total_items_i18n'] = sprintf(
				/* translators: Number of items. */
				_n( '%s item', '%s items', $this->_pagination_args['total_items'] ),
				number_format_i18n( $this->_pagination_args['total_items'] )
			);
		}
		if ( isset( $this->_pagination_args['total_pages'] ) ) {
			$response['total_pages']      = $this->_pagination_args['total_pages'];
			$response['total_pages_i18n'] = number_format_i18n( $this->_pagination_args['total_pages'] );
		}

		wp_send_json( $response );
	}
}

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
	 * Sets up the test fixture.
	 */
	public function set_up() {
		parent::set_up();

		add_filter( 'wp_list_table_class_name', array( $this, 'filter_wp_list_table_class_name' ), 10, 2 );
	}

	/**
	 * Cleans up the test fixture.
	 */
	public function tear_down() {
		remove_filter( 'wp_list_table_class_name', array( $this, 'filter_wp_list_table_class_name' ), 10 );

		parent::tear_down();
	}

	/**
	 * Filters the list table class name to use a test subclass with an interceptable response.
	 *
	 * @param string $class_name The list table class to use.
	 * @param array  $args       Arguments passed to _get_list_table().
	 * @return string
	 */
	public function filter_wp_list_table_class_name( $class_name, $args ) {
		if ( 'WP_Posts_List_Table' === $class_name ) {
			return 'Test_WP_Posts_List_Table';
		}

		return $class_name;
	}

	/**
	 * Tests fetching a list table via AJAX with no items.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list_no_items(): void {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Set up the $_GET request.
		$list_class = 'WP_Posts_List_Table';

		$_GET = array(
			'list_args'              => array(
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
		$this->assertSame( '0 items', $response['total_items_i18n'] );
		$this->assertSame( 0, $response['total_pages'] );
		$this->assertSame( '0', $response['total_pages_i18n'] );
	}

	/**
	 * Tests fetching a list table via AJAX with items.
	 *
	 * @ticket 65237
	 */
	public function test_wp_ajax_fetch_list_with_items(): void {
		// Become an administrator.
		$this->_setRole( 'administrator' );

		// Create a post.
		self::factory()->post->create( array( 'post_title' => 'Test Post' ) );

		$list_class = 'WP_Posts_List_Table';

		$_GET = array(
			'list_args'              => array(
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
		$this->assertStringContainsString( 'Test Post', $response['rows'] );
		$this->assertSame( '1 item', $response['total_items_i18n'] );
		$this->assertSame( 1, $response['total_pages'] );
		$this->assertSame( '1', $response['total_pages_i18n'] );
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
			'list_args'              => array(
				'class'  => $list_class,
				'screen' => array(
					'id' => 'edit-post',
				),
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
			'list_args'              => array(
				'class'  => $list_class,
				'screen' => array(
					'id' => 'edit-post',
				),
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
			'list_args'              => array(
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
