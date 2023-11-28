<?php

/**
 * Tests for the wp_scheduled_delete function.
 *
 * @group Functions.php
 *
 * @covers ::wp_scheduled_delete
 */
class Tests_Functions_wpScheduledDelete extends WP_UnitTestCase {

	static $comment_id;
	static $page_id;
	public function tear_down() {
		// Remove comment.
		if ( self::$comment_id ) {
			wp_delete_comment( self::$comment_id );
		}
		// Remove page.
		if ( self::$page_id ) {
			wp_delete_post( self::$page_id );
		}
		parent::tear_down();
	}

	/**
	 * Delete old trashed post/pages.
	 *
	 * @ticket 59938
	 *
	 */
	public function test_wp_scheduled_delete() {
		self::$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
			)
		);
		add_post_meta( self::$page_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_post_meta( self::$page_id, '_wp_trash_meta_status', 'published' );

		$this->assertNotEmpty( get_post( self::$page_id ) );

		wp_scheduled_delete();

		$this->assertEmpty( get_post( self::$page_id ) );
	}

	/**
	 * Don't delete old trashed post/pages if status not trash.
	 * Remove the trash meta status.
	 *
	 * @ticket 59938
	 *
	 */
	public function test_wp_scheduled_delete_not_trash() {
		self::$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'published',
			)
		);
		add_post_meta( self::$page_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_post_meta( self::$page_id, '_wp_trash_meta_status', 'published' );

		$this->assertNotEmpty( get_post( self::$page_id ) );

		wp_scheduled_delete();

		$this->assertNotEmpty( get_post( self::$page_id ) );
		$this->assertEmpty( get_post_meta( self::$page_id, '_wp_trash_meta_time', true ) );
		$this->assertEmpty( get_post_meta( self::$page_id, '_wp_trash_meta_status', true ) );
	}


	/**
	 * Don't delete old trashed post/pages if old enough.
	 *
	 * @ticket 59938
	 *
	 */
	public function test_wp_scheduled_delete_not_old() {
		self::$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
			)
		);
		add_post_meta( self::$page_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS ) );
		add_post_meta( self::$page_id, '_wp_trash_meta_status', 'published' );

		$this->assertNotEmpty( get_post( self::$page_id ) );

		wp_scheduled_delete();

		$this->assertNotEmpty( get_post( self::$page_id ) );
		$this->assertNotEmpty( get_post_meta( self::$page_id, '_wp_trash_meta_time', true ) );
		$this->assertNotEmpty( get_post_meta( self::$page_id, '_wp_trash_meta_status', true ) );
	}

	/**
	 * Delete old trashed comments.
	 *
	 * @ticket 59938
	 *
	 */
	public function test_wp_scheduled_delete_comment() {
		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 'trash',
			)
		);
		add_comment_meta( self::$comment_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_post_meta( self::$comment_id, '_wp_trash_meta_status', 'published' );

		$this->assertNotEmpty( get_comment( self::$comment_id ) );

		wp_scheduled_delete();

		$this->assertEmpty( get_comment( self::$comment_id ) );
	}

	/**
	 * Don't delete old trashed comments if status not trash.
	 * Remove the trash meta status.
	 *
	 * @ticket 59938
	 *
	 */
	public function test_wp_scheduled_delete_not_trash_comment() {
		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => '1',
			)
		);
		add_comment_meta( self::$comment_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_comment_meta( self::$comment_id, '_wp_trash_meta_status', 'published' );

		$this->assertNotEmpty( get_comment( self::$comment_id ) );

		wp_scheduled_delete();

		$this->assertNotEmpty( get_comment( self::$comment_id ) );
		$this->assertEmpty( get_comment_meta( self::$comment_id, '_wp_trash_meta_time', true ) );
		$this->assertEmpty( get_comment_meta( self::$comment_id, '_wp_trash_meta_status', true ) );
	}


	/**
	 * Don't delete old trashed comments if old enough.
	 *
	 * @ticket 59938
	 *
	 */
	public function test_wp_scheduled_delete_not_old_comment() {
		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 'trash',
			)
		);
		add_comment_meta( self::$comment_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS ) );
		add_comment_meta( self::$comment_id, '_wp_trash_meta_status', 'published' );

		$this->assertNotEmpty( get_comment( self::$comment_id ) );

		wp_scheduled_delete();

		$this->assertNotEmpty( get_comment( self::$comment_id ) );
		$this->assertNotEmpty( get_comment_meta( self::$comment_id, '_wp_trash_meta_time', true ) );
		$this->assertNotEmpty( get_comment_meta( self::$comment_id, '_wp_trash_meta_status', true ) );
	}
}
