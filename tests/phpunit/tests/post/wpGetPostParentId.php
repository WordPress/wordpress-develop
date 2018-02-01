<?php

/**
 * @group post
 */
class Tests_Post_WpGetPostParentId extends WP_UnitTestCase {
	/**
	 * Parent post ID.
	 *
	 * @var int
	 */
	public static $parent_post_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public static $post_id;

	public static function wpSetUpBeforeClass() {
		self::$parent_post_id = self::factory()->post->create();
		self::$post_id        = self::factory()->post->create( array( 'post_parent' => self::$parent_post_id ) );
	}

	public function test_wp_get_post_parent_id_with_post_object() {
		$post = get_post( self::$post_id );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertSame( self::$parent_post_id, wp_get_post_parent_id( $post ) );
	}

	public function test_wp_get_post_parent_id_with_post_id() {
		$this->assertSame( self::$parent_post_id, wp_get_post_parent_id( self::$post_id ) );
	}

	public function test_wp_get_post_parent_id_with_non_existing_id_default_to_global_post_id() {
		$GLOBALS['post'] = get_post( self::$post_id );
		$this->assertSame( self::$parent_post_id, wp_get_post_parent_id( 0 ) );
	}

	public function test_wp_get_post_parent_id_with_boolean_default_to_global_post_id() {
		$GLOBALS['post'] = get_post( self::$post_id );
		$this->assertSame( self::$parent_post_id, wp_get_post_parent_id( false ) );
	}

	public function test_wp_get_post_parent_id_with_string_default_to_false() {
		$GLOBALS['post'] = get_post( self::$post_id );
		$this->assertFalse( wp_get_post_parent_id( 'string' ) );
	}
}
