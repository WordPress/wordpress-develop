<?php

/**
 * Admin Ajax functions to be tested.
 */
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';

/**
 * Testing wp_ajax_get_tagcloud() functionality.
 *
 * @package WordPress
 * @subpackage UnitTests
 * @since 3.1.0
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_tagcloud
 */
class Tests_wp_ajax_get_tagcloud extends WP_Ajax_UnitTestCase {

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
		add_action( 'admin_init', array( $this, 'hook_ajax_handler' ), 1 );
	}

	/**
	 * Hooks the AJAX handler to admin_init.
	 */
	public function hook_ajax_handler(): void {
		if ( isset( $_POST['action'] ) && 'get-tagcloud' === $_POST['action'] ) {
			wp_ajax_get_tagcloud();
		}
	}

	/**
	 * Tests successful tag cloud generation.
	 *
	 * @ticket 65252
	 */
	public function test_get_tagcloud_success(): void {
		$factory = self::factory();
		wp_set_current_user( self::$admin_id );

		$tag_name = 'Test Tag';
		$tag_id   = $factory->term->create(
			array(
				'taxonomy' => 'post_tag',
				'name'     => $tag_name,
			)
		);

		$post_id = $factory->post->create();
		wp_set_post_tags( $post_id, array( $tag_id ) );

		$_POST = array(
			'action' => 'get-tagcloud',
			'tax'    => 'post_tag',
		);

		try {
			$this->_handleAjax( 'get-tagcloud' );
		} catch ( WPAjaxDieStopException $e ) {
			$response = $e->getMessage();
			$this->assertStringContainsString( 'wp-tag-cloud', $response );
			$this->assertStringContainsString( $tag_name, $response );
		} catch ( WPAjaxDieContinueException $e ) {
			$response = $this->_last_response;
			$this->assertStringContainsString( 'wp-tag-cloud', $response );
			$this->assertStringContainsString( $tag_name, $response );
		}
	}

	/**
	 * Tests tag cloud generation failure due to missing taxonomy.
	 *
	 * @ticket 65252
	 */
	public function test_get_tagcloud_missing_tax(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action' => 'get-tagcloud',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '0' );

		$this->_handleAjax( 'get-tagcloud' );
	}

	/**
	 * Tests tag cloud generation failure due to invalid taxonomy.
	 *
	 * @ticket 65252
	 */
	public function test_get_tagcloud_invalid_tax(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array(
			'action' => 'get-tagcloud',
			'tax'    => 'invalid_taxonomy',
		);

		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '0' );

		$this->_handleAjax( 'get-tagcloud' );
	}

	/**
	 * Tests tag cloud generation failure due to insufficient permissions.
	 *
	 * @ticket 65252
	 */
	public function test_get_tagcloud_insufficient_permissions(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array(
			'action' => 'get-tagcloud',
			'tax'    => 'post_tag',
		);

		try {
			$this->_handleAjax( 'get-tagcloud' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( '-1', $e->getMessage() );
		}
	}

	/**
	 * Tests tag cloud generation when no tags are found.
	 *
	 * @ticket 65252
	 */
	public function test_get_tagcloud_no_tags_found(): void {
		wp_set_current_user( self::$admin_id );

		// Ensure no tags exist for the taxonomy.
		$tags = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
			)
		);
		foreach ( $tags as $tag ) {
			wp_delete_term( $tag->term_id, 'post_tag' );
		}

		$tax_object = get_taxonomy( 'post_tag' );

		$_POST = array(
			'action' => 'get-tagcloud',
			'tax'    => 'post_tag',
		);

		try {
			$this->_handleAjax( 'get-tagcloud' );
		} catch ( WPAjaxDieStopException $e ) {
			$this->assertSame( $tax_object->labels->not_found, $e->getMessage() );
		} catch ( WPAjaxDieContinueException $e ) {
			$this->assertSame( $tax_object->labels->not_found, $e->getMessage() );
		}
	}
}
