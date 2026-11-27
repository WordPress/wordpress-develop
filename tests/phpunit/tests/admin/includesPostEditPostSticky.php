<?php

/**
 * Tests for how edit_post() handles the sticky state of a post.
 *
 * These tests document the current behavior: edit_post() updates the sticky
 * state after wp_update_post() (and therefore after the `save_post` action) has run.
 *
 * @group admin
 * @group post
 *
 * @covers ::edit_post
 */
class Tests_Admin_IncludesPostEditPostSticky extends WP_UnitTestCase {

	/**
	 * Builds the data array passed to edit_post().
	 *
	 * @param int    $post_id Post ID.
	 * @param string $sticky  Value of the "sticky" field. An empty string means not sticky.
	 * @return array
	 */
	private function get_post_data( $post_id, $sticky ) {
		return array(
			'post_ID'      => $post_id,
			'post_type'    => 'post',
			'post_title'   => 'Sticky test',
			'post_content' => 'Content',
			'post_status'  => 'publish',
			'sticky'       => $sticky,
		);
	}

	/**
	 * @ticket 28172
	 */
	public function test_edit_post_sticks_post() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();

		edit_post( $this->get_post_data( $post_id, '1' ) );

		$this->assertTrue( is_sticky( $post_id ) );
	}

	/**
	 * @ticket 28172
	 */
	public function test_edit_post_unsticks_post_when_sticky_is_empty() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();
		stick_post( $post_id );

		edit_post( $this->get_post_data( $post_id, '' ) );

		$this->assertFalse( is_sticky( $post_id ) );
	}

	/**
	 * @ticket 28172
	 */
	public function test_edit_post_does_not_change_sticky_state_without_edit_others_posts() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );
		$post_id = self::factory()->post->create( array( 'post_author' => $author_id ) );

		edit_post( $this->get_post_data( $post_id, '1' ) );
		$this->assertFalse( is_sticky( $post_id ), 'An author must not be able to stick a post.' );

		stick_post( $post_id );
		edit_post( $this->get_post_data( $post_id, '' ) );
		$this->assertTrue( is_sticky( $post_id ), 'An author must not be able to unstick a post.' );
	}

	/**
	 * The sticky state is updated after the `save_post` action has fired.
	 *
	 * @ticket 28172
	 */
	public function test_edit_post_updates_sticky_state_after_save_post() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();

		$sticky_during_save_post = null;
		add_action(
			'save_post',
			static function ( $saved_post_id ) use ( $post_id, &$sticky_during_save_post ) {
				if ( $saved_post_id === $post_id ) {
					$sticky_during_save_post = is_sticky( $post_id );
				}
			}
		);

		edit_post( $this->get_post_data( $post_id, '1' ) );

		$this->assertFalse( $sticky_during_save_post, 'The post is not yet sticky when save_post fires.' );
		$this->assertTrue( is_sticky( $post_id ), 'The post is sticky once edit_post() returns.' );
	}

	/**
	 * A `save_post` callback that unsticks a post is currently overridden by edit_post().
	 *
	 * @ticket 28172
	 */
	public function test_save_post_callback_unsticking_is_overridden_by_edit_post() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();

		add_action(
			'save_post',
			static function ( $saved_post_id ) {
				unstick_post( $saved_post_id );
			}
		);

		edit_post( $this->get_post_data( $post_id, '1' ) );

		$this->assertTrue( is_sticky( $post_id ) );
	}

	/**
	 * A `save_post` callback that sticks a post is currently overridden by edit_post().
	 *
	 * @ticket 28172
	 */
	public function test_save_post_callback_sticking_is_overridden_by_edit_post() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$post_id = self::factory()->post->create();

		add_action(
			'save_post',
			static function ( $saved_post_id ) {
				stick_post( $saved_post_id );
			}
		);

		edit_post( $this->get_post_data( $post_id, '' ) );

		$this->assertFalse( is_sticky( $post_id ) );
	}
}
