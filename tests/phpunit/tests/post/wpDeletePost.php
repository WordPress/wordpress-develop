<?php
/**
 * Test wp_delete_post() function
 *
 * @package WordPress
 * @subpackage Post
 *
 * @since 6.9.0
 */

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
	 * @var array{administrator: int, editor: int, contributor: int}
	 */
	protected static $user_ids;

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
	 * Tests wp_delete_post reassign hierarchical post type.
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

		$this->assertInstanceOf( WP_Post::class, wp_delete_post( $parent_page_id, true ) );
		$this->assertSame( $grandparent_page_id, get_post( $page_id )->post_parent );

		$this->assertInstanceOf( WP_Post::class, wp_delete_post( $grandparent_page_id, true ) );
		$this->assertSame( 0, get_post( $page_id )->post_parent );
	}

	/**
	 * Tests: "When I delete a future post using wp_delete_post( $post->ID ) it does not update the cron correctly."
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
		$this->assertInstanceOf( WP_Post::class, wp_delete_post( $post_id ) );

		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Helper function: return the timestamp(s) of cron jobs for the specified hook and post.
	 */
	private function next_schedule_for_post( $hook, int $post_id ) {
		return wp_next_scheduled( $hook, array( 0 => $post_id ) );
	}

	/**
	 * Tests that if the post_id is 0, wp_delete_post should return false.
	 *
	 * @ticket 63975
	 */
	public function test_wp_delete_post_short_circuit_on_post_id_zero() {
		$this->setExpectedIncorrectUsage( 'wp_delete_post' );
		$this->assertFalse( wp_delete_post( 0, true ) );
	}

	/**
	 * Tests wp_delete_post() when the post for the post_id has been already deleted.
	 */
	public function test_wp_delete_post_returns_false_for_invalid_post() {
		$post_id      = self::factory()->post->create();
		$deleted_post = wp_delete_post( $post_id, true );
		$this->assertInstanceOf( WP_Post::class, $deleted_post );
		$this->assertSame( $post_id, $deleted_post->ID );

		$this->assertNull( wp_delete_post( $post_id, true ) );
	}

	/**
	 * Tests actions triggered when deleting a post, even when a string ID is supplied.
	 *
	 * @ticket 63975
	 */
	public function test_wp_delete_post_actions() {
		$actions               = array(
			'before_delete_post',
			'delete_post_post',
			'delete_post',
			'deleted_post_post',
			'deleted_post',
			'after_delete_post',
		);
		$captured_action_args  = array();
		$initial_action_counts = array();
		foreach ( $actions as $action ) {
			$initial_action_counts[ $action ] = did_action( $action );
			add_action(
				$action,
				static function () use ( $action, &$captured_action_args ) {
					$captured_action_args[ $action ] = func_get_args();
				},
				10,
				PHP_INT_MAX
			);
		}

		$post_id      = self::factory()->post->create();
		$deleted_post = wp_delete_post( (string) $post_id, true );
		$this->assertInstanceOf( WP_Post::class, $deleted_post );

		foreach ( array( 'before_delete_post', 'delete_post_post', 'delete_post', 'deleted_post_post', 'deleted_post', 'after_delete_post' ) as $action ) {
			$this->assertSame( $initial_action_counts[ $action ] + 1, did_action( $action ), "Expected $action action count to increment by 1." );
			$this->assertCount( 2, $captured_action_args[ $action ], "Expected count for $action action" );
			$this->assertSame( $post_id, $captured_action_args[ $action ][0], "Expected post ID for $action action" );
			$this->assertInstanceOf( WP_Post::class, $captured_action_args[ $action ][1], "Expected class for $action action" );
			$this->assertSame( $post_id, $captured_action_args[ $action ][1]->ID, "Expected post ID for $action action" );
		}
	}

	/**
	 * Tests short-circuiting wp_delete_post() with pre_delete_post filter.
	 *
	 * @ticket @63975
	 */
	public function test_wp_delete_post_can_be_short_circuited() {
		$post_id = self::factory()->post->create();
		$filter  = function ( $check, WP_Post $post, bool $force_delete ) use ( $post_id ) {
			$this->assertNull( $check );
			$this->assertSame( $post_id, $post->ID );
			$this->assertTrue( $force_delete );
			return false;
		};

		add_filter( 'pre_delete_post', $filter, 10, 3 );
		$this->assertFalse( wp_delete_post( $post_id, true ) );
		$post = get_post( $post_id );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );
	}

	/**
	 * Tests that wp_delete_post() deletes associated comments.
	 */
	public function test_wp_delete_post_deletes_associated_comments() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'comment_type'    => 'comment',
			)
		);

		$this->assertInstanceOf( WP_Post::class, wp_delete_post( $post_id, true ) );

		$this->assertNull( get_comment( $comment_id ) );
	}

	/**
	 * Tests that upon deletion of a post, attachments should be reattached to the parent post.
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

		$this->assertInstanceOf( WP_Post::class, wp_delete_post( $post_id, true ) );
		clean_post_cache( $attachment_id );

		$this->assertSame( $parent_post_id, get_post( $attachment_id )->post_parent );
	}
}
