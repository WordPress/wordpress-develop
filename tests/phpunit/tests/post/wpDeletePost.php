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
}
