<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_add_link_category() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_add_link_category
 */
class Tests_wp_ajax_add_link_category extends WP_Ajax_UnitTestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Subscriber user ID.
	 *
	 * @var int
	 */
	protected static $subscriber_id;

	/**
	 * Setup test fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ): void {
		self::$admin_id      = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$subscriber_id = $factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Setup before each test method.
	 */
	public function set_up(): void {
		parent::set_up();
		update_option( 'link_manager_enabled', 1 );
		create_initial_taxonomies();
		add_action( 'admin_init', array( $this, 'hook_ajax_handler' ), 1 );
	}

	/**
	 * Hooks the AJAX handler to admin_init.
	 */
	public function hook_ajax_handler(): void {
		if ( isset( $_POST['action'] ) && 'add-link-category' === $_POST['action'] ) {
			wp_ajax_add_link_category( 'add-link-category' );
		}
	}

	/**
	 * Tests successful addition of a single link category.
	 *
	 * @ticket 65252
	 */
	public function test_add_link_category_success(): void {
		wp_set_current_user( self::$admin_id );

		$cat_name = 'New Link Category';
		$_POST    = array(
			'action'      => 'add-link-category',
			'newcat'      => $cat_name,
			'_ajax_nonce' => wp_create_nonce( 'add-link-category' ),
		);

		try {
			$this->_handleAjax( 'add-link-category' );
		} catch ( WPAjaxDieStopException $e ) {
			// Expecting XML response from WP_Ajax_Response.
			$response = $e->getMessage();
			$this->assertStringContainsString( '<wp_ajax>', $response );
			$this->assertStringContainsString( 'link-category', $response );
			$this->assertStringContainsString( esc_html( $cat_name ), $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( '<wp_ajax>', $response );
			$this->assertStringContainsString( 'link-category', $response );
			$this->assertStringContainsString( esc_html( $cat_name ), $response );
		}

		$term = get_term_by( 'name', $cat_name, 'link_category' );
		$this->assertNotFalse( $term, 'Link category should be created.' );
		$this->assertSame( $cat_name, $term->name );
	}

	/**
	 * Tests successful addition of multiple link categories.
	 *
	 * @ticket 65252
	 */
	public function test_add_link_category_multiple_success(): void {
		wp_set_current_user( self::$admin_id );

		$cat_names = array( 'Cat 1', 'Cat 2' );
		$_POST     = array(
			'action'      => 'add-link-category',
			'newcat'      => implode( ',', $cat_names ),
			'_ajax_nonce' => wp_create_nonce( 'add-link-category' ),
		);

		try {
			$this->_handleAjax( 'add-link-category' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			foreach ( $cat_names as $name ) {
				$this->assertStringContainsString( esc_html( $name ), $response );
			}
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			foreach ( $cat_names as $name ) {
				$this->assertStringContainsString( esc_html( $name ), $response );
			}
		}

		foreach ( $cat_names as $name ) {
			$term = get_term_by( 'name', $name, 'link_category' );
			$this->assertNotFalse( $term, "Link category '$name' should be created." );
		}
	}

	/**
	 * Tests addition failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_add_link_category_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'add-link-category',
			'newcat'      => 'Some Category',
			'_ajax_nonce' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( 'add-link-category' );
	}

	/**
	 * Tests addition failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_add_link_category_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$cat_name = 'Unauthorized Category';
		$_POST    = array(
			'action'      => 'add-link-category',
			'newcat'      => $cat_name,
			'_ajax_nonce' => wp_create_nonce( 'add-link-category' ),
		);

		try {
			$this->_handleAjax( 'add-link-category' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}

		$term = get_term_by( 'name', $cat_name, 'link_category' );
		$this->assertFalse( $term, 'Link category should NOT be created by subscriber.' );
	}

	/**
	 * Tests addition with empty name.
	 *
	 * @ticket 65252
	 */
	public function test_add_link_category_empty_name(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action'      => 'add-link-category',
			'newcat'      => '  ', // Empty name after trim
			'_ajax_nonce' => wp_create_nonce( 'add-link-category' ),
		);

		try {
			$this->_handleAjax( 'add-link-category' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			$this->assertStringContainsString( '<wp_ajax>', $response );
			$this->assertStringNotContainsString( 'link-category', $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( '<wp_ajax>', $response );
			$this->assertStringNotContainsString( 'link-category', $response );
		}
	}
}
