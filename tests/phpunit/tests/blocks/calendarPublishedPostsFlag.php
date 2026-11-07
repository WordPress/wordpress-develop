<?php
/**
 * Tests for the functions of the Calendar block that keep the flag of the published posts up to date.
 *
 * The functions are only defined on single sites.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 * @group ms-excluded
 */
class Tests_Blocks_CalendarPublishedPostsFlag extends WP_UnitTestCase {

	/**
	 * Value that the flag has when it was not updated.
	 */
	const STALE = 'stale';

	/**
	 * Removes the posts and sets the flag to the value of a flag that was not updated.
	 */
	public function set_up() {
		parent::set_up();

		$posts = get_posts(
			array(
				'numberposts' => -1,
				'post_status' => 'any',
				'post_type'   => 'any',
			)
		);

		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}

		$this->make_flag_stale();
	}

	/**
	 * Sets the flag to the value of a flag that was not updated.
	 */
	private function make_flag_stale() {
		update_option( 'wp_calendar_block_has_published_posts', self::STALE );
	}

	/**
	 * Tests that the flag is updated when a published post is deleted.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_post_on_delete
	 */
	public function test_should_update_the_flag_when_a_published_post_is_deleted() {
		$post_id = self::factory()->post->create();

		$this->make_flag_stale();

		block_core_calendar_update_has_published_post_on_delete( $post_id );

		$this->assertTrue( get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Tests that the flag is not updated when a post that does not count is deleted.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_post_on_delete
	 *
	 * @dataProvider data_posts_that_do_not_count
	 *
	 * @param array $args The arguments of the post.
	 */
	public function test_should_not_update_the_flag_when_a_post_that_does_not_count_is_deleted( $args ) {
		$post_id = self::factory()->post->create( $args );

		$this->make_flag_stale();

		block_core_calendar_update_has_published_post_on_delete( $post_id );

		$this->assertSame( self::STALE, get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_posts_that_do_not_count() {
		return array(
			'draft post'     => array( array( 'post_status' => 'draft' ) ),
			'private post'   => array( array( 'post_status' => 'private' ) ),
			'published page' => array( array( 'post_type' => 'page' ) ),
		);
	}

	/**
	 * Tests that the flag is not updated for a post that does not exist.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_post_on_delete
	 */
	public function test_should_not_update_the_flag_when_the_deleted_post_does_not_exist() {
		block_core_calendar_update_has_published_post_on_delete( 99999999 );

		$this->assertSame( self::STALE, get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Tests that the flag is updated when a post is published or unpublished.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_post_on_transition_post_status
	 *
	 * @dataProvider data_transitions_that_count
	 *
	 * @param string $new_status The new status of the post.
	 * @param string $old_status The old status of the post.
	 */
	public function test_should_update_the_flag_when_a_post_is_published_or_unpublished( $new_status, $old_status ) {
		$post = get_post( self::factory()->post->create() );

		$this->make_flag_stale();

		block_core_calendar_update_has_published_post_on_transition_post_status( $new_status, $old_status, $post );

		$this->assertNotSame( self::STALE, get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_transitions_that_count() {
		return array(
			'published from a draft' => array( 'publish', 'draft' ),
			'published from new'     => array( 'publish', 'new' ),
			'drafted from published' => array( 'draft', 'publish' ),
			'trashed from published' => array( 'trash', 'publish' ),
			'private from published' => array( 'private', 'publish' ),
			'published from private' => array( 'publish', 'private' ),
		);
	}

	/**
	 * Tests that the flag is left as it is when the status does not matter.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_post_on_transition_post_status
	 *
	 * @dataProvider data_transitions_that_do_not_count
	 *
	 * @param string $new_status The new status of the post.
	 * @param string $old_status The old status of the post.
	 */
	public function test_should_not_update_the_flag_when_the_status_does_not_matter( $new_status, $old_status ) {
		$post = get_post( self::factory()->post->create() );

		$this->make_flag_stale();

		block_core_calendar_update_has_published_post_on_transition_post_status( $new_status, $old_status, $post );

		$this->assertSame( self::STALE, get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_transitions_that_do_not_count() {
		return array(
			'same status'                 => array( 'publish', 'publish' ),
			'same status that is a draft' => array( 'draft', 'draft' ),
			'draft from pending'          => array( 'draft', 'pending' ),
			'trash from draft'            => array( 'trash', 'draft' ),
			'new draft'                   => array( 'draft', 'new' ),
		);
	}

	/**
	 * Tests that the flag is left as it is for a post type other than post.
	 *
	 * @ticket 66598
	 *
	 * @covers ::block_core_calendar_update_has_published_post_on_transition_post_status
	 */
	public function test_should_not_update_the_flag_for_a_page() {
		$page = get_post( self::factory()->post->create( array( 'post_type' => 'page' ) ) );

		$this->make_flag_stale();

		block_core_calendar_update_has_published_post_on_transition_post_status( 'publish', 'draft', $page );

		$this->assertSame( self::STALE, get_option( 'wp_calendar_block_has_published_posts' ) );
	}

	/**
	 * Tests that the functions are hooked.
	 *
	 * @ticket 66598
	 */
	public function test_should_hook_the_functions() {
		$this->assertSame( 10, has_action( 'delete_post', 'block_core_calendar_update_has_published_post_on_delete' ) );
		$this->assertSame( 10, has_action( 'transition_post_status', 'block_core_calendar_update_has_published_post_on_transition_post_status' ) );
	}

	/**
	 * Tests the flag when a post is published and unpublished.
	 *
	 * @ticket 66598
	 */
	public function test_should_keep_the_flag_up_to_date_when_a_post_is_published_and_unpublished() {
		$post_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertFalse( block_core_calendar_update_has_published_posts() );

		wp_publish_post( $post_id );

		$this->assertTrue( get_option( 'wp_calendar_block_has_published_posts' ), 'The flag should be true after the post is published.' );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			)
		);

		$this->assertFalse( get_option( 'wp_calendar_block_has_published_posts' ), 'The flag should be false after the post is unpublished.' );
	}

	/**
	 * Tests the flag when a post is created as published.
	 *
	 * @ticket 66598
	 */
	public function test_should_update_the_flag_when_a_published_post_is_created() {
		self::factory()->post->create();

		$this->assertTrue( get_option( 'wp_calendar_block_has_published_posts' ) );
	}
}
