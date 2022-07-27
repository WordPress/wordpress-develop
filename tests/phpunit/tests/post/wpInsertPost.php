<?php

/**
 * @group post
 * @covers ::wp_insert_post
 */
class Tests_Post_wpInsertPost extends WP_UnitTestCase {

	protected static $user_ids = array(
		'administrator' => null,
		'editor'        => null,
		'contributor'   => null,
	);

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

		$role = get_role( 'administrator' );
		$role->add_cap( 'publish_mapped_meta_caps' );
		$role->add_cap( 'publish_unmapped_meta_caps' );
	}

	public static function tear_down_after_class() {
		$role = get_role( 'administrator' );
		$role->remove_cap( 'publish_mapped_meta_caps' );
		$role->remove_cap( 'publish_unmapped_meta_caps' );

		parent::tear_down_after_class();
	}

	public function set_up() {
		parent::set_up();

		register_post_type(
			'mapped_meta_caps',
			array(
				'capability_type' => array( 'mapped_meta_cap', 'mapped_meta_caps' ),
				'map_meta_cap'    => true,
			)
		);

		register_post_type(
			'unmapped_meta_caps',
			array(
				'capability_type' => array( 'unmapped_meta_cap', 'unmapped_meta_caps' ),
				'map_meta_cap'    => false,
			)
		);

		register_post_type(
			'no_admin_caps',
			array(
				'capability_type' => array( 'no_admin_cap', 'no_admin_caps' ),
				'map_meta_cap'    => false,
			)
		);
	}

	/**
	 * Helper function: return the timestamp(s) of cron jobs for the specified hook and post.
	 */
	private function next_schedule_for_post( $hook, $post_id ) {
		return wp_next_scheduled( 'publish_future_post', array( 0 => (int) $post_id ) );
	}

	/**
	 * Helper function, unsets current user globally.
	 */
	private function unset_current_user() {
		global $current_user, $user_ID;

		$current_user = null;
		$user_ID      = null;
	}

	/**
	 * Test simple valid behavior: insert and get a post.
	 *
	 * @dataProvider data_vb_insert_get_delete
	 */
	public function test_vb_insert_get_delete( $post_type ) {
		register_post_type(
			'cpt',
			array(
				'taxonomies' => array( 'post_tag', 'ctax' ),
			)
		);
		register_taxonomy( 'ctax', 'cpt' );

		wp_set_current_user( self::$user_ids['editor'] );

		$data = array(
			'post_author'  => self::$user_ids['editor'],
			'post_status'  => 'publish',
			'post_content' => "{$post_type}_content",
			'post_title'   => "{$post_type}_title",
			'tax_input'    => array(
				'post_tag' => 'tag1,tag2',
				'ctax'     => 'cterm1,cterm2',
			),
			'post_type'    => $post_type,
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Fetch the post and make sure it matches.
		$post = get_post( $post_id );

		$this->assertSame( $data['post_content'], $post->post_content );
		$this->assertSame( $data['post_title'], $post->post_title );
		$this->assertSame( $data['post_status'], $post->post_status );
		$this->assertEquals( $data['post_author'], $post->post_author );

		// Test cache state.
		$post_cache = wp_cache_get( $post_id, 'posts' );
		$this->assertInstanceOf( 'stdClass', $post_cache );
		$this->assertSame( $post_id, $post_cache->ID );

		update_object_term_cache( $post_id, $post_type );
		$term_cache = wp_cache_get( $post_id, 'post_tag_relationships' );
		$this->assertIsArray( $term_cache );
		$this->assertCount( 2, $term_cache );

		$term_cache = wp_cache_get( $post_id, 'ctax_relationships' );
		if ( 'cpt' === $post_type ) {
			$this->assertIsArray( $term_cache );
			$this->assertCount( 2, $term_cache );
		} else {
			$this->assertFalse( $term_cache );
		}

		wp_delete_post( $post_id, true );

		$this->assertFalse( wp_cache_get( $post_id, 'posts' ) );
		$this->assertFalse( wp_cache_get( $post_id, 'post_tag_relationships' ) );
		$this->assertFalse( wp_cache_get( $post_id, 'ctax_relationships' ) );

		$GLOBALS['wp_taxonomies']['post_tag']->object_type = array( 'post' );
	}

	public function data_vb_insert_get_delete() {
		$post_types = array( 'post', 'cpt' );

		return $this->text_array_to_dataprovider( $post_types );
	}

	/**
	 * Insert a post with a future date, and make sure the status and cron schedule are correct.
	 */
	public function test_vb_insert_future() {
		$future_date = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Fetch the post and make sure it matches.
		$post = get_post( $post_id );

		$this->assertSame( $data['post_content'], $post->post_content );
		$this->assertSame( $data['post_title'], $post->post_title );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// There should be a publish_future_post hook scheduled on the future date.
		$this->assertSame( $future_date, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Insert a post with a future date, and make sure the status and cron schedule are correct.
	 */
	public function test_vb_insert_future_over_dst() {
		// Some magic days - one DST one not.
		$future_date_1 = strtotime( 'June 21st +1 year' );
		$future_date_2 = strtotime( 'Jan 11th +1 year' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date_1}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Fetch the post and make sure has the correct date and status.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

		// Now save it again with a date further in the future.
		$data['ID']            = $post_id;
		$data['post_date']     = date_format( date_create( "@{$future_date_2}" ), 'Y-m-d H:i:s' );
		$data['post_date_gmt'] = null;
		wp_update_post( $data );

		// Fetch the post again and make sure it has the new post_date.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// And the correct date on the cron job.
		$this->assertSame( $future_date_2, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Future post bug: posts get published at the wrong time if you edit the timestamp.
	 *
	 * @ticket 4710
	 */
	public function test_vb_insert_future_edit_bug() {
		$future_date_1 = strtotime( '+1 day' );
		$future_date_2 = strtotime( '+2 day' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date_1}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Fetch the post and make sure has the correct date and status.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

		// Now save it again with a date further in the future.
		$data['ID']            = $post_id;
		$data['post_date']     = date_format( date_create( "@{$future_date_2}" ), 'Y-m-d H:i:s' );
		$data['post_date_gmt'] = null;
		wp_update_post( $data );

		// Fetch the post again and make sure it has the new post_date.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// And the correct date on the cron job.
		$this->assertSame( $future_date_2, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Insert a draft post with a future date, and make sure no cron schedule is set.
	 */
	public function test_vb_insert_future_draft() {
		$future_date = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'draft',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Fetch the post and make sure it matches.
		$post = get_post( $post_id );

		$this->assertSame( $data['post_content'], $post->post_content );
		$this->assertSame( $data['post_title'], $post->post_title );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// There should be a publish_future_post hook scheduled on the future date.
		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

	}

	/**
	 * Insert a future post, then edit and change it to draft, and make sure cron gets it right.
	 */
	public function test_vb_insert_future_change_to_draft() {
		$future_date_1 = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date_1}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Fetch the post and make sure has the correct date and status.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

		// Now save it again with status set to draft.
		$data['ID']          = $post_id;
		$data['post_status'] = 'draft';
		wp_update_post( $data );

		// Fetch the post again and make sure it has the new post_date.
		$post = get_post( $post_id );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// And the correct date on the cron job.
		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Insert a future post, then edit and change the status, and make sure cron gets it right.
	 *
	 * @dataProvider data_vb_insert_future_change_status
	 */
	public function test_vb_insert_future_change_status( $status ) {
		$future_date_1 = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => "{$status}_content",
			'post_title'   => "{$status}_title",
			'post_date'    => date_format( date_create( "@{$future_date_1}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Fetch the post and make sure has the correct date and status.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

		// Now save it again with status changed.
		$data['ID']          = $post_id;
		$data['post_status'] = $status;
		wp_update_post( $data );

		// Fetch the post again and make sure it has the new post_date.
		$post = get_post( $post_id );
		$this->assertSame( $status, $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// And the correct date on the cron job.
		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	public function data_vb_insert_future_change_status() {
		$statuses = array(
			'draft',
			'static',
			'object',
			'attachment',
			'inherit',
			'pending',
		);

		return $this->text_array_to_dataprovider( $statuses );
	}

	/**
	 * Insert a draft post with a future date, and make sure no cron schedule is set.
	 */
	public function test_vb_insert_future_private() {
		$future_date = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'private',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		// Fetch the post and make sure it matches.
		$post = get_post( $post_id );

		$this->assertSame( $data['post_content'], $post->post_content );
		$this->assertSame( $data['post_title'], $post->post_title );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// There should be a publish_future_post hook scheduled on the future date.
		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * Insert a post with an invalid date, make sure it fails.
	 *
	 * @ticket 17180
	 */
	public function test_vb_insert_invalid_date() {
		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => '2012-02-30 00:00:00',
		);

		// Test both return paths with or without WP_Error.
		$post_id = wp_insert_post( $data, true );
		$this->assertWPError( $post_id );
		$this->assertSame( 'invalid_date', $post_id->get_error_code() );

		$post_id = wp_insert_post( $data );
		$this->assertSame( 0, $post_id );
	}

	/**
	 * Insert a future post, then edit and change it to private, and make sure cron gets it right.
	 */
	public function test_vb_insert_future_change_to_private() {
		$future_date_1 = strtotime( '+1 day' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_date'    => date_format( date_create( "@{$future_date_1}" ), 'Y-m-d H:i:s' ),
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Fetch the post and make sure has the correct date and status.
		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->next_schedule_for_post( 'publish_future_post', $post_id ) );

		// Now save it again with status set to draft.
		$data['ID']          = $post_id;
		$data['post_status'] = 'private';
		wp_update_post( $data );

		// Fetch the post again and make sure it has the new post_date.
		$post = get_post( $post_id );
		$this->assertSame( 'private', $post->post_status );
		$this->assertSame( $data['post_date'], $post->post_date );

		// And the correct date on the cron job.
		$this->assertFalse( $this->next_schedule_for_post( 'publish_future_post', $post_id ) );
	}

	/**
	 * @ticket 5305
	 */
	public function test_wp_insert_post_should_not_allow_a_bare_numeric_slug_that_might_conflict_with_a_date_archive_when_generating_from_an_empty_post_title() {
		$this->set_permalink_structure( '/%postname%/' );

		$post_id = wp_insert_post(
			array(
				'post_title'   => '',
				'post_content' => 'test',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);

		$post = get_post( $post_id );

		$this->assertSame( "$post_id-2", $post->post_name );
	}

	/**
	 * @ticket 5305
	 * @ticket 33392
	 */
	public function test_wp_insert_post_should_invalidate_post_cache_before_generating_guid_when_post_name_is_empty_and_is_generated_from_the_post_ID() {
		register_post_type( 'wptests_pt' );

		$post_id = wp_insert_post(
			array(
				'post_title'  => '',
				'post_type'   => 'wptests_pt',
				'post_status' => 'publish',
			)
		);

		$post = get_post( $post_id );

		$this->assertStringContainsString( 'wptests_pt=' . $post_id, $post->guid );
	}

	/**
	 * @ticket 55877
	 * @covers ::wp_insert_post
	 */
	public function test_wp_insert_post_should_not_trigger_warning_for_pending_posts_with_unknown_cpt() {
		$post_id = wp_insert_post(
			array(
				'post_title'  => 'title',
				'post_type'   => 'unknown',
				'post_status' => 'pending',
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
	}

	/**
	 * @ticket 20451
	 */
	public function test_wp_insert_post_with_meta_input() {
		$post_id = wp_insert_post(
			array(
				'post_title'   => '',
				'post_content' => 'test',
				'post_status'  => 'publish',
				'post_type'    => 'post',
				'meta_input'   => array(
					'hello' => 'world',
					'foo'   => 'bar',
				),
			)
		);

		$this->assertSame( 'world', get_post_meta( $post_id, 'hello', true ) );
		$this->assertSame( 'bar', get_post_meta( $post_id, 'foo', true ) );
	}

	/**
	 * "When I delete a future post using wp_delete_post( $post->ID ) it does not update the cron correctly."
	 *
	 * @ticket 5364
	 * @covers ::wp_delete_post
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
	 * Bug: permalink doesn't work if post title is empty.
	 *
	 * Might only fail if the post ID is greater than four characters.
	 *
	 * @ticket 5305
	 */
	public function test_permalink_without_title() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$data = array(
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => '',
			'post_date'    => '2007-10-31 06:15:00',
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		// Permalink should include the post ID at the end.
		$expected = get_option( 'siteurl' ) . '/2007/10/31/' . $post_id . '/';
		$this->assertSame( $expected, get_permalink( $post_id ) );
	}

	/**
	 * @ticket 23708
	 */
	public function test_get_post_ancestors_within_loop() {
		global $post;

		$parent_id = self::factory()->post->create();
		$post      = self::factory()->post->create_and_get(
			array(
				'post_parent' => $parent_id,
			)
		);

		$this->assertSame( array( $parent_id ), get_post_ancestors( 0 ) );
	}

	/**
	 * @ticket 23474
	 * @covers ::wp_update_post
	 */
	public function test_update_invalid_post_id() {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id, ARRAY_A );

		$post['ID'] = 123456789;

		$this->assertSame( 0, wp_insert_post( $post ) );
		$this->assertSame( 0, wp_update_post( $post ) );

		$this->assertInstanceOf( 'WP_Error', wp_insert_post( $post, true ) );
		$this->assertInstanceOf( 'WP_Error', wp_update_post( $post, true ) );

	}

	/**
	 * @ticket 19373
	 */
	public function test_insert_programmatic_sanitized() {
		$this->unset_current_user();

		register_taxonomy( 'test_tax', 'post' );

		$title = 'title';
		$data  = array(
			'post_author'  => self::$user_ids['editor'],
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => $title,
			'tax_input'    => array(
				'test_tax' => array( 'term', 'term2', 'term3' ),
			),
		);

		$post_id = wp_insert_post( $data, true, true );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertEquals( self::$user_ids['editor'], $post->post_author );
		$this->assertSame( $title, $post->post_title );
	}

	/**
	 * @ticket 31168
	 */
	public function test_wp_insert_post_default_comment_ping_status_open() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( 'open', $post->comment_status );
		$this->assertSame( 'open', $post->ping_status );
	}

	/**
	 * @ticket 31168
	 */
	public function test_wp_insert_post_page_default_comment_ping_status_closed() {
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( 'closed', $post->ping_status );
	}

	/**
	 * @ticket 31168
	 */
	public function test_wp_insert_post_cpt_default_comment_ping_status_open() {
		register_post_type(
			'cpt',
			array(
				'supports' => array( 'comments', 'trackbacks' ),
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'cpt',
			)
		);
		$post    = get_post( $post_id );

		_unregister_post_type( 'cpt' );

		$this->assertSame( 'open', $post->comment_status );
		$this->assertSame( 'open', $post->ping_status );
	}

	/**
	 * @ticket 31168
	 */
	public function test_wp_insert_post_cpt_default_comment_ping_status_closed() {
		register_post_type( 'cpt' );

		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_type'   => 'cpt',
			)
		);
		$post    = get_post( $post_id );

		_unregister_post_type( 'cpt' );

		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( 'closed', $post->ping_status );
	}

	/**
	 * If a post is updated without providing a post_name param,
	 * a new slug should not be generated.
	 *
	 * @ticket 34865
	 */
	public function test_post_updates_without_slug_provided() {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Stuff',
				'post_status' => 'publish',
			)
		);

		$data = array(
			'ID'         => $post_id,
			'post_title' => 'Stuff and Things',
		);

		wp_insert_post( $data );

		$updated_post = get_post( $post_id );
		// Ensure changing the post_title didn't modify the post_name.
		$this->assertSame( 'stuff', $updated_post->post_name );
	}

	/**
	 * @ticket 32585
	 */
	public function test_wp_insert_post_author_zero() {
		$post_id = self::factory()->post->create( array( 'post_author' => 0 ) );

		$this->assertEquals( 0, get_post( $post_id )->post_author );
	}

	/**
	 * @ticket 32585
	 */
	public function test_wp_insert_post_author_null() {
		wp_set_current_user( self::$user_ids['editor'] );

		$post_id = self::factory()->post->create( array( 'post_author' => null ) );

		$this->assertEquals( self::$user_ids['editor'], get_post( $post_id )->post_author );
	}

	/**
	 * @ticket 15946
	 */
	public function test_wp_insert_post_should_respect_post_date_gmt() {
		$data = array(
			'post_status'   => 'publish',
			'post_content'  => 'content',
			'post_title'    => 'title',
			'post_date_gmt' => '2014-01-01 12:00:00',
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $data );

		$post = get_post( $post_id );

		$this->assertSame( $data['post_content'], $post->post_content );
		$this->assertSame( $data['post_title'], $post->post_title );
		$this->assertSame( get_date_from_gmt( $data['post_date_gmt'] ), $post->post_date );
		$this->assertSame( $data['post_date_gmt'], $post->post_date_gmt );
	}

	/**
	 * Test ensuring that the post_name (UUID) is preserved when wp_insert_post()/wp_update_post() is called.
	 *
	 * @see _wp_customize_changeset_filter_insert_post_data()
	 * @ticket 30937
	 */
	public function test_wp_insert_post_for_customize_changeset_should_not_drop_post_name() {
		$this->assertSame( 10, has_filter( 'wp_insert_post_data', '_wp_customize_changeset_filter_insert_post_data' ) );

		$changeset_data = array(
			'blogname' => array(
				'value' => 'Hello World',
			),
		);

		wp_set_current_user( self::$user_ids['contributor'] );

		$uuid    = wp_generate_uuid4();
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'customize_changeset',
				'post_name'    => strtoupper( $uuid ),
				'post_content' => wp_json_encode( $changeset_data ),
			)
		);
		$this->assertSame( $uuid, get_post( $post_id )->post_name, 'Expected lower-case UUID4 to be inserted.' );
		$this->assertSame( $changeset_data, json_decode( get_post( $post_id )->post_content, true ) );

		$changeset_data['blogname']['value'] = 'Hola Mundo';
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_status'  => 'draft',
				'post_content' => wp_json_encode( $changeset_data ),
			)
		);
		$this->assertSame( $uuid, get_post( $post_id )->post_name, 'Expected post_name to not have been dropped for drafts.' );
		$this->assertSame( $changeset_data, json_decode( get_post( $post_id )->post_content, true ) );

		$changeset_data['blogname']['value'] = 'Hallo Welt';
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_status'  => 'pending',
				'post_content' => wp_json_encode( $changeset_data ),
			)
		);
		$this->assertSame( $uuid, get_post( $post_id )->post_name, 'Expected post_name to not have been dropped for pending.' );
		$this->assertSame( $changeset_data, json_decode( get_post( $post_id )->post_content, true ) );
	}

	/**
	 * @ticket 48113
	 */
	public function test_insert_post_should_respect_date_floating_post_status_arg() {
		register_post_status( 'floating', array( 'date_floating' => true ) );

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'floating',
				'post_date'     => null,
				'post_date_gmt' => null,
			)
		);

		$post = get_post( $post_id );
		self::assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );
	}

	/**
	 * @ticket 48113
	 */
	public function test_insert_post_should_respect_date_floating_post_status_arg_not_set() {
		register_post_status( 'not-floating', array( 'date_floating' => false ) );

		$post_id = self::factory()->post->create(
			array(
				'post_status'   => 'floating',
				'post_date'     => null,
				'post_date_gmt' => null,
			)
		);

		$post = get_post( $post_id );
		self::assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( $post->post_date_gmt ),
			2,
			'The dates should be equal'
		);
	}

	/**
	 * Test ensuring that wp_update_post() does not unintentionally modify post tags
	 * if the post has several tags with the same name but different slugs.
	 *
	 * Tags should only be modified if 'tags_input' parameter was explicitly provided,
	 * and is different from the existing tags.
	 *
	 * @ticket 45121
	 * @covers ::wp_update_post
	 */
	public function test_update_post_should_only_modify_post_tags_if_different_tags_input_was_provided() {
		$tag_1 = wp_insert_term( 'wp_update_post_tag', 'post_tag', array( 'slug' => 'wp_update_post_tag_1' ) );
		$tag_2 = wp_insert_term( 'wp_update_post_tag', 'post_tag', array( 'slug' => 'wp_update_post_tag_2' ) );
		$tag_3 = wp_insert_term( 'wp_update_post_tag', 'post_tag', array( 'slug' => 'wp_update_post_tag_3' ) );

		$post_id = self::factory()->post->create(
			array(
				'tags_input' => array( $tag_1['term_id'], $tag_2['term_id'] ),
			)
		);

		$post = get_post( $post_id );

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $tag_1['term_id'], $tag_2['term_id'] ), $tags );

		wp_update_post( $post );

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $tag_1['term_id'], $tag_2['term_id'] ), $tags );

		wp_update_post(
			array(
				'ID'         => $post->ID,
				'tags_input' => array( $tag_2['term_id'], $tag_3['term_id'] ),
			)
		);

		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'ids' ) );
		$this->assertSameSets( array( $tag_2['term_id'], $tag_3['term_id'] ), $tags );
	}

	/**
	 * @ticket 52187
	 */
	public function test_insert_empty_post_date() {
		$post_date_gmt = '2020-12-29 10:11:45';
		$invalid_date  = '2020-12-41 14:15:27';

		// Empty post_date_gmt with floating status
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'draft',
			)
		);
		$post    = get_post( $post_id );
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( $post->post_date ),
			2,
			'The dates should be equal'
		);
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		$post_id = self::factory()->post->create(
			array(
				'post_date_gmt' => '0000-00-00 00:00:00',
				'post_status'   => 'draft',
			)
		);
		$post    = get_post( $post_id );
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( $post->post_date ),
			2,
			'The dates should be equal'
		);
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		// Empty post_date_gmt without floating status
		$post_id = self::factory()->post->create(
			array(
				'post_status' => 'publish',
			)
		);
		$post    = get_post( $post_id );
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( $post->post_date ),
			2,
			'The dates should be equal'
		);
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( get_gmt_from_date( $post->post_date ) ),
			2,
			'The dates should be equal'
		);

		$post_id = self::factory()->post->create(
			array(
				'post_date_gmt' => '0000-00-00 00:00:00',
				'post_status'   => 'publish',
			)
		);
		$post    = get_post( $post_id );
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( $post->post_date ),
			2,
			'The dates should be equal'
		);
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( get_gmt_from_date( $post->post_date ) ),
			2,
			'The dates should be equal'
		);

		// Valid post_date_gmt
		$post_id = self::factory()->post->create(
			array(
				'post_date_gmt' => $post_date_gmt,
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( get_date_from_gmt( $post_date_gmt ), $post->post_date );
		$this->assertSame( $post_date_gmt, $post->post_date_gmt );

		// Invalid post_date_gmt
		$post_id = self::factory()->post->create(
			array(
				'post_date_gmt' => $invalid_date,
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( '1970-01-01 00:00:00', $post->post_date );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );
	}

	/**
	 * @ticket 52187
	 */
	public function test_insert_valid_post_date() {
		$post_date     = '2020-12-28 11:26:35';
		$post_date_gmt = '2020-12-29 10:11:45';
		$invalid_date  = '2020-12-41 14:15:27';

		// Empty post_date_gmt with floating status
		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'draft',
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( $post_date, $post->post_date );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $post_date,
				'post_date_gmt' => '0000-00-00 00:00:00',
				'post_status'   => 'draft',
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( $post_date, $post->post_date );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );

		// Empty post_date_gmt without floating status
		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $post_date,
				'post_status' => 'publish',
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( $post_date, $post->post_date );
		$this->assertSame( get_gmt_from_date( $post_date ), $post->post_date_gmt );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $post_date,
				'post_date_gmt' => '0000-00-00 00:00:00',
				'post_status'   => 'publish',
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( $post_date, $post->post_date );
		$this->assertSame( get_gmt_from_date( $post_date ), $post->post_date_gmt );

		// Valid post_date_gmt
		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $post_date,
				'post_date_gmt' => $post_date_gmt,
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( $post_date, $post->post_date );
		$this->assertSame( $post_date_gmt, $post->post_date_gmt );

		// Invalid post_date_gmt
		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $post_date,
				'post_date_gmt' => $invalid_date,
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( $post_date, $post->post_date );
		$this->assertSame( '0000-00-00 00:00:00', $post->post_date_gmt );
	}

	/**
	 * @ticket 52187
	 */
	public function test_insert_invalid_post_date() {
		$post_date     = '2020-12-28 11:26:35';
		$post_date_gmt = '2020-12-29 10:11:45';
		$invalid_date  = '2020-12-41 14:15:27';

		// Empty post_date_gmt with floating status
		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $invalid_date,
				'post_status' => 'draft',
			)
		);
		$this->assertSame( 0, $post_id );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $invalid_date,
				'post_date_gmt' => '0000-00-00 00:00:00',
				'post_status'   => 'draft',
			)
		);
		$this->assertSame( 0, $post_id );

		// Empty post_date_gmt without floating status
		$post_id = self::factory()->post->create(
			array(
				'post_date'   => $invalid_date,
				'post_status' => 'publish',
			)
		);
		$this->assertSame( 0, $post_id );

		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $invalid_date,
				'post_date_gmt' => '0000-00-00 00:00:00',
				'post_status'   => 'publish',
			)
		);
		$this->assertSame( 0, $post_id );

		// Valid post_date_gmt
		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $invalid_date,
				'post_date_gmt' => $post_date_gmt,
			)
		);
		$this->assertSame( 0, $post_id );

		// Invalid post_date_gmt
		$post_id = self::factory()->post->create(
			array(
				'post_date'     => $invalid_date,
				'post_date_gmt' => $invalid_date,
			)
		);
		$this->assertSame( 0, $post_id );
	}

	/**
	 * @ticket 11863
	 */
	public function test_trashing_a_post_should_add_trashed_suffix_to_post_name() {
		$trashed_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $trashed_about_page_id );
		$this->assertSame( 'about__trashed', get_post( $trashed_about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	public function test_trashed_suffix_should_be_added_to_post_with__trashed_in_slug() {
		$trashed_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
				'post_name'   => 'foo__trashed__foo',
			)
		);
		wp_trash_post( $trashed_about_page_id );
		$this->assertSame( 'foo__trashed__foo__trashed', get_post( $trashed_about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	public function test_trashed_posts_original_post_name_should_be_reassigned_after_untrashing() {
		$about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $about_page_id );

		wp_untrash_post( $about_page_id );
		$this->assertSame( 'about', get_post( $about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	public function test_creating_a_new_post_should_add_trashed_suffix_to_post_name_of_trashed_posts_with_the_desired_slug() {
		$trashed_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'trash',
			)
		);

		$about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'about__trashed', get_post( $trashed_about_page_id )->post_name );
		$this->assertSame( 'about', get_post( $about_page_id )->post_name );
	}

	/**
	 * @ticket 11863
	 */
	public function test_untrashing_a_post_with_a_stored_desired_post_name_should_get_its_post_name_suffixed_if_another_post_has_taken_the_desired_post_name() {
		$about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $about_page_id );

		$another_about_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'About',
				'post_status' => 'publish',
			)
		);

		wp_untrash_post( $about_page_id );
		wp_update_post(
			array(
				'ID'          => $about_page_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( 'about', get_post( $another_about_page_id )->post_name );
		$this->assertSame( 'about-2', get_post( $about_page_id )->post_name );
	}

	/**
	 * @ticket 23022
	 * @dataProvider data_various_post_statuses
	 */
	public function test_untrashing_a_post_should_always_restore_it_to_draft_status( $post_status ) {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $post_status,
			)
		);

		wp_trash_post( $page_id );
		wp_untrash_post( $page_id );

		$this->assertSame( 'draft', get_post( $page_id )->post_status );
	}

	/**
	 * @ticket 23022
	 * @dataProvider data_various_post_statuses
	 */
	public function test_wp_untrash_post_status_filter_restores_post_to_correct_status( $post_status ) {
		add_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => $post_status,
			)
		);

		wp_trash_post( $page_id );
		wp_untrash_post( $page_id );

		remove_filter( 'wp_untrash_post_status', 'wp_untrash_post_set_previous_status', 10, 3 );

		$this->assertSame( $post_status, get_post( $page_id )->post_status );
	}

	/**
	 * Data for testing the ability for users to set the post slug.
	 *
	 * @return array Array of test arguments.
	 */
	public function data_various_post_types() {
		$post_types = array(
			'mapped_meta_caps',
			'unmapped_meta_caps',
			'post',
		);

		return $this->text_array_to_dataprovider( $post_types );
	}

	/**
	 * Data for testing post statuses.
	 *
	 * @return array Array of test arguments.
	 */
	public function data_various_post_statuses() {
		$post_statuses = array(
			'draft',
			'pending',
			'private',
			'publish',
		);

		return $this->text_array_to_dataprovider( $post_statuses );
	}

	/**
	 * Test contributor making changes to the pending post slug.
	 *
	 * @ticket 42464
	 * @dataProvider data_various_post_types
	 */
	public function test_contributor_cannot_set_post_slug( $post_type ) {
		wp_set_current_user( self::$user_ids['contributor'] );

		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Jefferson claim: nice to have Washington on your side.',
				'post_content' => "I’m in the cabinet. I am complicit in watching him grabbin’ at power and kiss it.\n\nIf Washington isn’t gon’ listen to disciplined dissidents, this is the difference: this kid is out!",
				'post_type'    => $post_type,
				'post_name'    => 'new-washington',
				'post_status'  => 'pending',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );

		// Now update the post.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Hamilton has Washington on side: Jefferson',
				'post_name'  => 'edited-washington',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test administrator making changes to the pending post slug.
	 *
	 * @ticket 42464
	 * @dataProvider data_various_post_types
	 */
	public function test_administrator_can_set_post_slug( $post_type ) {
		wp_set_current_user( self::$user_ids['administrator'] );

		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'What is the Conner Project?',
				'post_content' => 'Evan Hansen’s last link to his friend Conner is a signature on his broken arm.',
				'post_type'    => $post_type,
				'post_name'    => 'dear-evan-hansen-explainer',
				'post_status'  => 'pending',
			)
		);

		$expected = 'dear-evan-hansen-explainer';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );

		// Now update the post.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Conner Project to close',
				'post_name'  => 'dear-evan-hansen-spoiler',
			)
		);

		$expected = 'dear-evan-hansen-spoiler';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Test administrator making changes to a pending post slug for a post type they don't
	 * have permission to publish.
	 *
	 * These assertions failed prior to ticket #42464.
	 *
	 * @ticket 42464
	 */
	public function test_administrator_cannot_set_post_slug_on_post_type_they_cannot_publish() {
		wp_set_current_user( self::$user_ids['administrator'] );

		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Everything is legal in New Jersey',
				'post_content' => 'Shortly before his death, Philip Hamilton was heard to claim everything was legal in the garden state.',
				'post_type'    => 'no_admin_caps',
				'post_name'    => 'yet-another-duel',
				'post_status'  => 'pending',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );

		// Now update the post.
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'Ten things illegal in New Jersey',
				'post_name'  => 'foreshadowing-in-nj',
			)
		);

		$expected = '';
		$actual   = get_post_field( 'post_name', $post_id );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 25347
	 */
	public function test_scheduled_post_with_a_past_date_should_be_published() {

		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$post_id = $this->factory()->post->create(
			array(
				'post_date_gmt' => $now->modify( '-1 year' )->format( 'Y-m-d H:i:s' ),
				'post_status'   => 'future',
			)
		);

		$this->assertSame( 'publish', get_post_status( $post_id ) );

		$post_id = $this->factory()->post->create(
			array(
				'post_date_gmt' => $now->modify( '+50 years' )->format( 'Y-m-d H:i:s' ),
				'post_status'   => 'future',
			)
		);

		$this->assertSame( 'future', get_post_status( $post_id ) );
	}
}
