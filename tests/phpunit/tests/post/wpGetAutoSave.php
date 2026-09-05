<?php

/**
 * @group post
 */
class Tests_Post_wpGetPostAutosave extends WP_UnitTestCase {

	/**
	 * Admin user ID.
	 *
	 * @var int
	 */
	protected static $admin_id;

	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	protected static $editor_id;

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$admin_id  = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );

		wp_set_current_user( self::$admin_id );
		self::$post_id = $factory->post->create( array( 'post_status' => 'publish' ) );
	}

	/**
	 * Test when no autosave exists for a post.
	 *
	 * @ticket 62658
	 */
	public function test_no_autosave_exists() {
		$autosave = wp_get_post_autosave( self::$post_id );
		$this->assertFalse( $autosave, 'Expected no autosave.' );
	}

	/**
	 * Test when an autosave exists for a post.
	 *
	 * @ticket 62658
	 */
	public function test_autosave_exists() {
		$autosave_id = $this->factory()->post->create(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_parent'  => self::$post_id,
				'post_author'  => self::$admin_id,
				'post_content' => 'Autosaved content',
				'post_name'    => self::$post_id . '-autosave-v1',
			)
		);

		$autosave = wp_get_post_autosave( self::$post_id );

		$this->assertInstanceOf( 'WP_Post', $autosave );
		$this->assertSame( $autosave_id, $autosave->ID, 'Autosave ID does not match.' );
		$this->assertSame( self::$post_id, (int) $autosave->post_parent, 'Post parent ID does not match.' );
	}

	/**
	 * Test when an autosave exists for a specific user.
	 *
	 * @ticket 62658
	 */
	public function test_autosave_for_specific_user() {
		$autosave_id = $this->factory()->post->create(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_parent'  => self::$post_id,
				'post_author'  => self::$editor_id,
				'post_content' => 'Editor-specific autosave',
				'post_name'    => self::$post_id . '-autosave-v1',
			)
		);

		$autosave = wp_get_post_autosave( self::$post_id, self::$editor_id );

		$this->assertInstanceOf( 'WP_Post', $autosave );
		$this->assertSame( self::$editor_id, (int) $autosave->post_author, 'Post author does not match.' );
		$this->assertSame( $autosave_id, $autosave->ID, 'Autosave ID does not match.' );
	}

	/**
	 * Test when an autosave is updated.
	 *
	 * @ticket 62658
	 */
	public function test_autosave_exists_update_caches() {
		$autosave_id = $this->factory()->post->create(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_parent'  => self::$post_id,
				'post_author'  => self::$admin_id,
				'post_content' => 'Autosaved content',
				'post_name'    => self::$post_id . '-autosave-v1',
			)
		);

		$autosave = wp_get_post_autosave( self::$post_id );

		$this->assertInstanceOf( 'WP_Post', $autosave );
		$this->assertSame( $autosave_id, $autosave->ID, 'Autosave ID does not match.' );
		$this->assertSame( self::$post_id, (int) $autosave->post_parent, 'Post parent ID does not match.' );
		$this->assertSame( 'Autosaved content', $autosave->post_content, 'Post content does not match.' );

		wp_update_post(
			array(
				'ID'           => $autosave->ID,
				'post_content' => 'Autosaved content updated',
			)
		);

		$autosave = wp_get_post_autosave( self::$post_id );
		$this->assertInstanceOf( 'WP_Post', $autosave );
		$this->assertSame( 'Autosaved content updated', $autosave->post_content, 'Post content does not match.' );
	}

	/**
	 * Test when an autosave is deleted
	 *
	 * @ticket 62658
	 */
	public function test_autosave_exists_and_deleted() {
		$autosave_id = $this->factory()->post->create(
			array(
				'post_type'    => 'revision',
				'post_status'  => 'inherit',
				'post_parent'  => self::$post_id,
				'post_author'  => self::$admin_id,
				'post_content' => 'Autosaved content',
				'post_name'    => self::$post_id . '-autosave-v1',
			)
		);

		$autosave = wp_get_post_autosave( self::$post_id );

		$this->assertInstanceOf( 'WP_Post', $autosave );
		$this->assertSame( $autosave_id, $autosave->ID, 'Autosave ID does not match.' );
		$this->assertSame( self::$post_id, (int) $autosave->post_parent, 'Post parent ID does not match.' );
		$this->assertSame( 'Autosaved content', $autosave->post_content, 'Post content does not match.' );

		wp_delete_post( $autosave->ID, true );

		$autosave = wp_get_post_autosave( self::$post_id );
		$this->assertFalse( $autosave, 'Autosave should not exist' );
	}
}
