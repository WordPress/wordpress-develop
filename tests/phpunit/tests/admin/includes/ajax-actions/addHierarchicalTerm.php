<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing _wp_ajax_add_hierarchical_term() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::_wp_ajax_add_hierarchical_term
 */
class Tests_wp_ajax_add_hierarchical_term extends WP_Ajax_UnitTestCase {

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
		// The function is called by the action 'wp_ajax_add-category' etc.
		// For testing, we can hook it to a known action or call it directly via _handleAjax.
		add_action( 'admin_init', '_wp_ajax_add_hierarchical_term', 1 );
	}

	/**
	 * Tests adding a hierarchical term (category) successfully.
	 *
	 * @ticket 65252
	 */
	public function test_add_hierarchical_term_success(): void {
		wp_set_current_user( self::$admin_id );

		$taxonomy  = 'category';
		$term_name = 'New Category';
		$action    = 'add-' . $taxonomy;

		$_POST = array(
			'action'                   => $action,
			'newcategory'              => $term_name,
			'_ajax_nonce-add-category' => wp_create_nonce( $action ),
		);

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$term = get_term_by( 'name', $term_name, $taxonomy );
		$this->assertNotFalse( $term, 'Term should be created.' );
		$this->assertSame( $term_name, $term->name );
	}

	/**
	 * Tests adding a child hierarchical term successfully.
	 *
	 * @ticket 65252
	 */
	public function test_add_hierarchical_term_child_success(): void {
		wp_set_current_user( self::$admin_id );

		$taxonomy  = 'category';
		$parent_id = $this->factory->category->create();
		$term_name = 'Child Category';
		$action    = 'add-' . $taxonomy;

		$_POST = array(
			'action'                   => $action,
			'newcategory'              => $term_name,
			'newcategory_parent'       => $parent_id,
			'_ajax_nonce-add-category' => wp_create_nonce( $action ),
		);

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		$term = get_term_by( 'name', $term_name, $taxonomy );
		$this->assertNotFalse( $term, 'Child term should be created.' );
		$this->assertEquals( $parent_id, $term->parent, 'Parent ID mismatch.' );
	}

	/**
	 * Tests adding multiple hierarchical terms at once.
	 *
	 * @ticket 65252
	 */
	public function test_add_hierarchical_term_multiple(): void {
		wp_set_current_user( self::$admin_id );

		$taxonomy   = 'category';
		$term_names = 'Cat A, Cat B, Cat C';
		$action     = 'add-' . $taxonomy;

		$_POST = array(
			'action'                   => $action,
			'newcategory'              => $term_names,
			'_ajax_nonce-add-category' => wp_create_nonce( $action ),
		);

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieStopException $e ) {
		} catch ( WPAjaxDieContinueException $e ) {
		}

		foreach ( explode( ',', $term_names ) as $name ) {
			$term = get_term_by( 'name', trim( $name ), $taxonomy );
			$this->assertNotFalse( $term, "Term '$name' should be created." );
		}
	}

	/**
	 * Tests adding a hierarchical term with an invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_add_hierarchical_term_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$taxonomy  = 'category';
		$term_name = 'Invalid Nonce Category';
		$action    = 'add-' . $taxonomy;

		$_POST = array(
			'action'                   => $action,
			'newcategory'              => $term_name,
			'_ajax_nonce-add-category' => 'invalid-nonce',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );

		$this->_handleAjax( $action );

		$term = get_term_by( 'name', $term_name, $taxonomy );
		$this->assertFalse( $term, 'Term should NOT be created with invalid nonce.' );
	}

	/**
	 * Tests adding a hierarchical term with insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_add_hierarchical_term_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$taxonomy  = 'category';
		$term_name = 'No Permission Category';
		$action    = 'add-' . $taxonomy;

		$_POST = array(
			'action'                   => $action,
			'newcategory'              => $term_name,
			'_ajax_nonce-add-category' => wp_create_nonce( $action ),
		);

		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}

		$term = get_term_by( 'name', $term_name, $taxonomy );
		$this->assertFalse( $term, 'Term should NOT be created by subscriber.' );
	}
}
