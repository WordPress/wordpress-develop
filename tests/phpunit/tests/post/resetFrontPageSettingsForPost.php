<?php
/**
 * Tests for _reset_front_page_settings_for_post().
 *
 * @group post
 * @ticket 19958
 *
 * @covers ::_reset_front_page_settings_for_post
 */
class Tests_Post_ResetFrontPageSettingsForPost extends WP_UnitTestCase {

	public function test_trashing_front_page_resets_front_page_settings() {
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );

		wp_trash_post( $page_id );

		$this->assertSame( 'posts', get_option( 'show_on_front' ), 'show_on_front should fall back to posts.' );
		$this->assertSame( 0, (int) get_option( 'page_on_front' ), 'page_on_front should be cleared.' );
	}

	public function test_deleting_posts_page_resets_posts_page_setting() {
		$front_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$posts_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );
		update_option( 'page_for_posts', $posts_id );

		wp_delete_post( $posts_id, true );

		$this->assertSame( 0, (int) get_option( 'page_for_posts' ), 'page_for_posts should be cleared.' );
		$this->assertSame( 'page', get_option( 'show_on_front' ), 'show_on_front should not change.' );
		$this->assertSame( $front_id, (int) get_option( 'page_on_front' ), 'page_on_front should not change.' );
	}

	public function test_trashing_other_page_keeps_front_page_settings() {
		$front_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$other_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_id );

		wp_trash_post( $other_id );

		$this->assertSame( 'page', get_option( 'show_on_front' ), 'show_on_front should not change.' );
		$this->assertSame( $front_id, (int) get_option( 'page_on_front' ), 'page_on_front should not change.' );
	}

	public function test_trashing_sticky_post_unsticks_it() {
		$post_id = self::factory()->post->create();
		stick_post( $post_id );

		wp_trash_post( $post_id );

		$this->assertFalse( is_sticky( $post_id ) );
	}
}
