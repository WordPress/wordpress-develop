<?php

/**
 * @group post
 * @group template
 *
 * @covers ::get_post_parent
 * @covers ::has_post_parent
 */
class Tests_Post_GetPostParent extends WP_UnitTestCase {

	/**
	 * @ticket 33045
	 */
	public function test_get_post_parent() {
		$post = array(
			'post_status' => 'publish',
			'post_type'   => 'page',
		);

		// Insert two initial posts.
		$parent_id = self::factory()->post->create( $post );
		$child_id  = self::factory()->post->create( $post );

		// Test if the function returns null by default.
		$parent = get_post_parent( $child_id );
		$this->assertNull( $parent );

		// Update child post with a parent.
		wp_update_post(
			array(
				'ID'          => $child_id,
				'post_parent' => $parent_id,
			)
		);

		// Test if the function returns the parent object.
		$parent = get_post_parent( $child_id );
		$this->assertNotNull( $parent );
		$this->assertSame( $parent_id, $parent->ID );
	}

	/**
	 * @ticket 33045
	 */
	public function test_has_post_parent() {
		$post = array(
			'post_status' => 'publish',
			'post_type'   => 'page',
		);

		// Insert two initial posts.
		$parent_id = self::factory()->post->create( $post );
		$child_id  = self::factory()->post->create( $post );

		// Test if the function returns false by default.
		$parent = has_post_parent( $child_id );
		$this->assertFalse( $parent );

		// Update child post with a parent.
		wp_update_post(
			array(
				'ID'          => $child_id,
				'post_parent' => $parent_id,
			)
		);

		// Test if the function returns true for a child post.
		$parent = has_post_parent( $child_id );
		$this->assertTrue( $parent );
	}
}
