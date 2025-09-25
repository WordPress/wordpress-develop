<?php

/**
 * Tests for the wp_scheduled_delete() function.
 *
 * @group functions
 *
 * @covers ::wp_scheduled_delete
 */
class Tests_Functions_wpScheduledDelete extends WP_UnitTestCase {

	protected static $comment_id;
	protected static $page_id;

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
	 * Tests that old trashed posts/pages are deleted.
	 *
	 * @ticket 59938
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

		$this->assertInstanceOf( 'WP_Post', get_post( self::$page_id ) );

		wp_scheduled_delete();

		$this->assertNull( get_post( self::$page_id ) );
	}

	/**
	 * Tests that old trashed posts/pages are not deleted if status is not 'trash'.
	 *
	 * Ensures that the trash meta status is removed.
	 *
	 * @ticket 59938
	 */
	public function test_wp_scheduled_delete_status_not_trash() {
		self::$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'published',
			)
		);
		add_post_meta( self::$page_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_post_meta( self::$page_id, '_wp_trash_meta_status', 'published' );

		$this->assertInstanceOf( 'WP_Post', get_post( self::$page_id ) );

		wp_scheduled_delete();

		$this->assertInstanceOf( 'WP_Post', get_post( self::$page_id ) );
		$this->assertSame( '', get_post_meta( self::$page_id, '_wp_trash_meta_time', true ) );
		$this->assertSame( '', get_post_meta( self::$page_id, '_wp_trash_meta_status', true ) );
	}


	/**
	 * Tests that old trashed posts/pages are not deleted if not old enough.
	 *
	 * @ticket 59938
	 */
	public function test_wp_scheduled_delete_page_not_old_enough() {
		self::$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'trash',
			)
		);
		add_post_meta( self::$page_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS - 1 ) );
		add_post_meta( self::$page_id, '_wp_trash_meta_status', 'published' );

		$this->assertInstanceOf( 'WP_Post', get_post( self::$page_id ) );

		wp_scheduled_delete();

		$this->assertInstanceOf( 'WP_Post', get_post( self::$page_id ) );
		$this->assertIsNumeric( get_post_meta( self::$page_id, '_wp_trash_meta_time', true ) );
		$this->assertSame( 'published', get_post_meta( self::$page_id, '_wp_trash_meta_status', true ) );
	}

	/**
	 * Tests that old trashed comments are deleted.
	 *
	 * @ticket 59938
	 */
	public function test_wp_scheduled_delete_comment() {
		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 'trash',
			)
		);
		add_comment_meta( self::$comment_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_post_meta( self::$comment_id, '_wp_trash_meta_status', 'published' );

		$this->assertInstanceOf( 'WP_Comment', get_comment( self::$comment_id ) );

		wp_scheduled_delete();

		$this->assertNull( get_comment( self::$comment_id ) );
	}

	/**
	 * Tests that old trashed comments are not deleted if status is not 'trash'.
	 *
	 * Ensures that the trash meta status is removed.
	 *
	 * @ticket 59938
	 */
	public function test_wp_scheduled_delete_comment_status_not_trash() {
		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => '1',
			)
		);
		add_comment_meta( self::$comment_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS + 1 ) );
		add_comment_meta( self::$comment_id, '_wp_trash_meta_status', 'published' );

		$this->assertInstanceOf( 'WP_Comment', get_comment( self::$comment_id ) );

		wp_scheduled_delete();

		$this->assertInstanceOf( 'WP_Comment', get_comment( self::$comment_id ) );
		$this->assertSame( '', get_comment_meta( self::$comment_id, '_wp_trash_meta_time', true ) );
		$this->assertSame( '', get_comment_meta( self::$comment_id, '_wp_trash_meta_status', true ) );
	}


	/**
	 * Tests that old trashed comments are not deleted if not old enough.
	 *
	 * @ticket 59938
	 */
	public function test_wp_scheduled_delete_comment_not_old_enough() {
		self::$comment_id = self::factory()->comment->create(
			array(
				'comment_approved' => 'trash',
			)
		);
		add_comment_meta( self::$comment_id, '_wp_trash_meta_time', time() - ( DAY_IN_SECONDS * EMPTY_TRASH_DAYS - 1 ) );
		add_comment_meta( self::$comment_id, '_wp_trash_meta_status', 'published' );

		$this->assertInstanceOf( 'WP_Comment', get_comment( self::$comment_id ) );

		wp_scheduled_delete();

		$this->assertInstanceOf( 'WP_Comment', get_comment( self::$comment_id ) );
		$this->assertIsNumeric( get_comment_meta( self::$comment_id, '_wp_trash_meta_time', true ) );
		$this->assertSame( 'published', get_comment_meta( self::$comment_id, '_wp_trash_meta_status', true ) );
	}
}
