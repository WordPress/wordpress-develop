<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_delete_tag() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_delete_tag
 */
class Tests_wp_ajax_delete_tag extends WP_Ajax_UnitTestCase {

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
		add_action( 'admin_init', 'wp_ajax_delete_tag', 1 );
	}

	/**
	 * Tests successful tag deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_tag_success(): void {
		wp_set_current_user( self::$admin_id );

		$tag_id = $this->factory->tag->create();

		$_POST = array(
			'tag_ID'      => $tag_id,
			'taxonomy'    => 'post_tag',
			'_ajax_nonce' => wp_create_nonce( "delete-tag_$tag_id" ),
		);

		try {
			$this->_handleAjax( 'delete_tag' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertNull( get_term( $tag_id, 'post_tag' ), 'Tag should be deleted.' );
	}

	/**
	 * Tests successful category deletion.
	 *
	 * @ticket 65252
	 */
	public function test_delete_category_success(): void {
		wp_set_current_user( self::$admin_id );

		$cat_id = $this->factory->category->create();

		$_POST = array(
			'tag_ID'      => $cat_id,
			'taxonomy'    => 'category',
			'_ajax_nonce' => wp_create_nonce( "delete-tag_$cat_id" ),
		);

		try {
			$this->_handleAjax( 'delete_tag' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '1', $e->getMessage(), 'AJAX response should be 1 (success).' );
		}

		$this->assertNull( get_term( $cat_id, 'category' ), 'Category should be deleted.' );
	}

	/**
	 * Tests tag deletion failure due to invalid nonce.
	 *
	 * @ticket 65252
	 */
	public function test_delete_tag_invalid_nonce(): void {
		wp_set_current_user( self::$admin_id );

		$tag_id = $this->factory->tag->create();

		$_POST = array(
			'tag_ID'      => $tag_id,
			'taxonomy'    => 'post_tag',
			'_ajax_nonce' => 'invalid-nonce',
		);

		try {
			$this->_handleAjax( 'delete_tag' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (invalid nonce).' );
		}

		$this->assertNotNull( get_term( $tag_id, 'post_tag' ), 'Tag should NOT be deleted.' );
	}

	/**
	 * Tests tag deletion failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_delete_tag_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$tag_id = $this->factory->tag->create();

		$_POST = array(
			'tag_ID'      => $tag_id,
			'taxonomy'    => 'post_tag',
			'_ajax_nonce' => wp_create_nonce( "delete-tag_$tag_id" ),
		);

		try {
			$this->_handleAjax( 'delete_tag' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 (insufficient permissions).' );
		}

		$this->assertNotNull( get_term( $tag_id, 'post_tag' ), 'Tag should NOT be deleted.' );
	}

	/**
	 * Tests tag deletion with non-existent tag ID.
	 *
	 * @ticket 65252
	 */
	public function test_delete_tag_non_existent_id(): void {
		wp_set_current_user( self::$admin_id );

		$tag_id = 99999;

		$_POST = array(
			'tag_ID'      => $tag_id,
			'taxonomy'    => 'post_tag',
			'_ajax_nonce' => wp_create_nonce( "delete-tag_$tag_id" ),
		);

		try {
			$this->_handleAjax( 'delete_tag' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage(), 'AJAX response should be -1 because permission check fails for non-existent tag.' );
		}
	}
}
