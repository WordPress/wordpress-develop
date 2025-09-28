<?php
/**
 * Test wp_delete_post() function
 *
 * @package WordPress
 * @subpackage Post
 *
 * @since 6.9.0
 */

use SebastianBergmann\RecursionContext\InvalidArgumentException;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Class to Test wp_delete_post() function
 *
 * @group post
 * @covers ::wp_delete_post
 */
class Tests_Post_WpDeletePost extends WP_UnitTestCase {

	/**
	 * User IDs for the test.
	 *
	 * @var array{administrator: null, editor: null, contributor: null}
	 */
	protected static $user_ids = array(
		'administrator' => null,
		'editor'        => null,
		'contributor'   => null,
	);

	/**
	 * Set up before class.
	 *
	 * @param WP_UnitTest_Factory $factory The Unit Test Factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_ids = array(
			'administrator' => $factory->user->create(
				array(
					'role' => 'administrator',
				)
			),
			'editor'        => $factory->user->create(
				array(
					'role' => 'editor',
				)
			),
			'contributor'   => $factory->user->create(
				array(
					'role' => 'contributor',
				)
			),
		);
	}

	/**
	 * Test wp_delete_post reassign hierarchical post type
	 */
	public function test_wp_delete_post_reassign_hierarchical_post_type() {
		$grandparent_page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$parent_page_id      = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $grandparent_page_id,
			)
		);
		$page_id             = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent_page_id,
			)
		);

		$this->assertSame( $parent_page_id, get_post( $page_id )->post_parent );

		wp_delete_post( $parent_page_id, true );
		$this->assertSame( $grandparent_page_id, get_post( $page_id )->post_parent );

		wp_delete_post( $grandparent_page_id, true );
		$this->assertSame( 0, get_post( $page_id )->post_parent );
	}

	/**
	 * "When I delete a future post using wp_delete_post( $post->ID ) it does not update the cron correctly."
	 *
	 * @ticket 5364
	 */
	public function test_delete_future_post_cron() {
		$future_date = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

		// Now delete the post and make sure the cron entry is removed.
		wp_delete_post( $post_id );

		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Helper function: return the timestamp(s) of cron jobs for the specified hook and post.
	 */
	private function next_schedule_for_post( $hook, $post_id ) {
		return wp_next_scheduled( 'publish_future_post', array( 0 => (int) $post_id ) );
	}

	/**
	 * If the post_id is 0, wp_delete_post should return false
	 *
	 * @ticket 63975
	 */
	public function test_wp_delete_post_shortcircuit_on_post_id_zero() {
		$this->assertFalse( wp_delete_post( 0, true ) );
	}

	/**
	 * Test that wp_delete_post when the post_id has been already deleted.
	 */
	public function test_wp_delete_post_returns_false_for_invalid_post() {
		$post_id = self::factory()->post->create();
		wp_delete_post( $post_id, true );

		$this->assertNull( wp_delete_post( $post_id, true ) );
	}

	/**
	 * Shortcircuit wp_delete_post with pre_delete_post filter
	 */
	public function test_wp_delete_post_can_be_short_circuited() {
		$post_id = self::factory()->post->create();
		$filter  = function () {
			return 'avoid_deletion';
		};

		add_filter( 'pre_delete_post', $filter, 10, 3 );
		wp_delete_post( $post_id, true );
		remove_filter( 'pre_delete_post', $filter, 10 );

		$this->assertNotNull( get_post( $post_id ) );
	}

	/**
	 * Check that wp_delete_post deletes associated comments
	 */
	public function test_wp_delete_post_deletes_associated_comments() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'comment',
			)
		);

		wp_delete_post( $post_id, true );

		$this->assertNull( get_comment( $comment_id ) );
	}

	/**
	 * On deletion of a post, attachments should be reattached to the parent post
	 */
	public function test_wp_delete_post_reassigns_attachments_to_parent() {
		$parent_post_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$post_id        = self::factory()->post->create(
			array(
				'post_parent' => $parent_post_id,
				'post_type'   => 'page',
			)
		);

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
			)
		);

		wp_delete_post( $post_id, true );
		clean_post_cache( $attachment_id );

		$this->assertSame( $parent_post_id, get_post( $attachment_id )->post_parent );
	}
}
