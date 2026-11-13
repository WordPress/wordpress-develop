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
 *
 * @group ajax
 *
 * @covers ::wp_ajax_get_tagcloud
 */
class Tests_Admin_Includes_AjaxActions_getTagcloud extends WP_Ajax_UnitTestCase {

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
	 * @ticket 65252
	 */
	public function test_get_tagcloud_returns_cloud_of_used_tags(): void {
		wp_set_current_user( self::$admin_id );

		$tag_id = self::factory()->tag->create( array( 'name' => 'Used Tag' ) );
		wp_set_post_tags( self::factory()->post->create(), array( $tag_id ) );
		$unused_id = self::factory()->tag->create( array( 'name' => 'Unused Tag' ) );

		$_POST = array( 'tax' => 'post_tag' );

		$response = $this->get_ajax_response( 'get-tagcloud' );

		$this->assertStringContainsString( "<ul class='wp-tag-cloud' role='list'>", $response );
		$this->assertStringContainsString( "href=\"#\" role=\"button\" class=\"tag-cloud-link tag-link-{$tag_id} ", $response );
		$this->assertStringContainsString( '>Used Tag</a>', $response );
		$this->assertStringNotContainsString( "tag-link-{$unused_id} ", $response );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_tagcloud_returns_not_found_label_when_no_tags_are_used(): void {
		wp_set_current_user( self::$admin_id );

		self::factory()->tag->create( array( 'name' => 'Unused Tag' ) );

		$_POST = array( 'tax' => 'post_tag' );

		$this->assertSame( get_taxonomy( 'post_tag' )->labels->not_found, $this->get_ajax_response( 'get-tagcloud' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_tagcloud_returns_zero_without_taxonomy(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array();

		$this->assertSame( '0', $this->get_ajax_response( 'get-tagcloud' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_tagcloud_returns_zero_for_unknown_taxonomy(): void {
		wp_set_current_user( self::$admin_id );

		$_POST = array( 'tax' => 'invalid_taxonomy' );

		$this->assertSame( '0', $this->get_ajax_response( 'get-tagcloud' ) );
	}

	/**
	 * @ticket 65252
	 */
	public function test_get_tagcloud_rejects_user_without_capability(): void {
		wp_set_current_user( self::$subscriber_id );

		$_POST = array( 'tax' => 'post_tag' );

		$this->assertSame( '-1', $this->get_ajax_response( 'get-tagcloud' ) );
	}
}
