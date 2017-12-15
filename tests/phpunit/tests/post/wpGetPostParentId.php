<?php

/**
 * @group post
 */
class Tests_Post_WpGetPostParentId extends WP_UnitTestCase {

	public function test_wp_get_post_parent_id_with_post_object() {
		$p1 = self::factory()->post->create();
		$p2 = self::factory()->post->create( array( 'post_parent' => $p1 ) );
		$post = get_post( $p2 );
		$this->assertTrue( $post instanceof WP_Post );
		$this->assertEquals( $p1, wp_get_post_parent_id( $post ) );
	}

	public function test_wp_get_post_parent_id_with_post_id() {
		$p1 = self::factory()->post->create();
		$p2 = self::factory()->post->create( array( 'post_parent' => $p1 ) );
		$this->assertEquals( $p1, wp_get_post_parent_id( $p2 ) );
	}

	public function test_wp_get_post_parent_id_with_non_existing_id_default_to_global_post_id() {
		$p1 = self::factory()->post->create();
		$GLOBALS['post'] = self::factory()->post->create( array( 'post_parent' => $p1 ) );
		$this->assertEquals( $p1, wp_get_post_parent_id( 0 ) );
		unset( $GLOBALS['post'] );
	}

	public function test_wp_get_post_parent_id_with_boolean_default_to_global_post_id() {
		$p1 = self::factory()->post->create();
		$GLOBALS['post'] = self::factory()->post->create( array( 'post_parent' => $p1 ) );
		$this->assertEquals( $p1, wp_get_post_parent_id( false ) );
		unset( $GLOBALS['post'] );
	}

	public function test_wp_get_post_parent_id_with_string_default_to_false() {
		$p1 = self::factory()->post->create();
		$GLOBALS['post'] = self::factory()->post->create( array( 'post_parent' => $p1 ) );
		$this->assertFalse( wp_get_post_parent_id( 'string' ) );
		unset( $GLOBALS['post'] );
	}
}
