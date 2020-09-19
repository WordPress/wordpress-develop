<?php

/**
 * test wp-includes/post.php
 *
 * @group post
 */
class Tests_Post extends WP_UnitTestCase {
	protected static $editor_id;
	protected static $grammarian_id;

	public static function wpSetUpBeforeClass( $factory ) {
		self::$editor_id = $factory->user->create( array( 'role' => 'editor' ) );

		add_role(
			'grammarian',
			'Grammarian',
			array(
				'read'                 => true,
				'edit_posts'           => true,
				'edit_others_posts'    => true,
				'edit_published_posts' => true,
			)
		);

		self::$grammarian_id = $factory->user->create( array( 'role' => 'grammarian' ) );
	}

	public static function wpTearDownAfterClass() {
		remove_role( 'grammarian' );
	}

	function setUp() {
		parent::setUp();

		wp_set_current_user( self::$editor_id );
		_set_cron_array( array() );

		$this->post_ids = array();
	}

	/**
	 * Helper function: return the timestamp(s) of cron jobs for the specified hook and post.
	 */
	function _next_schedule_for_post( $hook, $id ) {
		return wp_next_scheduled( 'publish_future_post', array( 0 => intval( $id ) ) );
	}

	/**
	 * Helper function, unsets current user globally.
	 */
	function _unset_current_user() {
		global $current_user, $user_ID;

		$current_user = null;
		$user_ID      = null;
	}

	/**
	 * Test simple valid behavior: insert and get a post.
	 */
	function test_vb_insert_get_delete() {
		register_post_type( 'cpt', array( 'taxonomies' => array( 'post_tag', 'ctax' ) ) );
		register_taxonomy( 'ctax', 'cpt' );
		$post_types = array( 'post', 'cpt' );

		foreach ( $post_types as $post_type ) {
			$post = array(
				'post_author'  => self::$editor_id,
				'post_status'  => 'publish',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
				'tax_input'    => array(
					'post_tag' => 'tag1,tag2',
					'ctax'     => 'cterm1,cterm2',
				),
				'post_type'    => $post_type,
			);

			// Insert a post and make sure the ID is OK.
			$id = wp_insert_post( $post );
			$this->assertTrue( is_numeric( $id ) );
			$this->assertTrue( $id > 0 );

			// Fetch the post and make sure it matches.
			$out = get_post( $id );

			$this->assertSame( $post['post_content'], $out->post_content );
			$this->assertSame( $post['post_title'], $out->post_title );
			$this->assertSame( $post['post_status'], $out->post_status );
			$this->assertEquals( $post['post_author'], $out->post_author );

			// Test cache state.
			$pcache = wp_cache_get( $id, 'posts' );
			$this->assertInstanceOf( 'stdClass', $pcache );
			$this->assertSame( $id, $pcache->ID );

			update_object_term_cache( $id, $post_type );
			$tcache = wp_cache_get( $id, 'post_tag_relationships' );
			$this->assertInternalType( 'array', $tcache );
			$this->assertSame( 2, count( $tcache ) );

			$tcache = wp_cache_get( $id, 'ctax_relationships' );
			if ( 'cpt' === $post_type ) {
				$this->assertInternalType( 'array', $tcache );
				$this->assertSame( 2, count( $tcache ) );
			} else {
				$this->assertFalse( $tcache );
			}

			wp_delete_post( $id, true );
			$this->assertFalse( wp_cache_get( $id, 'posts' ) );
			$this->assertFalse( wp_cache_get( $id, 'post_tag_relationships' ) );
			$this->assertFalse( wp_cache_get( $id, 'ctax_relationships' ) );
		}

		$GLOBALS['wp_taxonomies']['post_tag']->object_type = array( 'post' );
	}

	/**
	 * Insert a post with a future date, and make sure the status and cron schedule are correct.
	 */
	function test_vb_insert_future() {
		$future_date = strtotime( '+1 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;
		// dmp( _get_cron_array() );
		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( $post['post_content'], $out->post_content );
		$this->assertSame( $post['post_title'], $out->post_title );
		$this->assertSame( 'future', $out->post_status );
		$this->assertEquals( $post['post_author'], $out->post_author );
		$this->assertSame( $post['post_date'], $out->post_date );

		// There should be a publish_future_post hook scheduled on the future date.
		$this->assertSame( $future_date, $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * Insert a post with a future date, and make sure the status and cron schedule are correct.
	 */
	function test_vb_insert_future_over_dst() {
		// Some magic days - one DST one not.
		$future_date_1 = strtotime( 'June 21st +1 year' );
		$future_date_2 = strtotime( 'Jan 11th +1 year' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date_1 ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;

		// Fetch the post and make sure has the correct date and status.
		$out = get_post( $id );
		$this->assertSame( 'future', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->_next_schedule_for_post( 'publish_future_post', $id ) );

		// Now save it again with a date further in the future.

		$post['ID']            = $id;
		$post['post_date']     = strftime( '%Y-%m-%d %H:%M:%S', $future_date_2 );
		$post['post_date_gmt'] = null;
		wp_update_post( $post );

		// Fetch the post again and make sure it has the new post_date.
		$out = get_post( $id );
		$this->assertSame( 'future', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// And the correct date on the cron job.
		$this->assertSame( $future_date_2, $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * Future post bug: posts get published at the wrong time if you edit the timestamp.
	 *
	 * @ticket 4710
	 */
	function test_vb_insert_future_edit_bug() {
		$future_date_1 = strtotime( '+1 day' );
		$future_date_2 = strtotime( '+2 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date_1 ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;

		// Fetch the post and make sure has the correct date and status.
		$out = get_post( $id );
		$this->assertSame( 'future', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->_next_schedule_for_post( 'publish_future_post', $id ) );

		// Now save it again with a date further in the future.

		$post['ID']            = $id;
		$post['post_date']     = strftime( '%Y-%m-%d %H:%M:%S', $future_date_2 );
		$post['post_date_gmt'] = null;
		wp_update_post( $post );

		// Fetch the post again and make sure it has the new post_date.
		$out = get_post( $id );
		$this->assertSame( 'future', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// And the correct date on the cron job.
		$this->assertSame( $future_date_2, $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * Insert a draft post with a future date, and make sure no cron schedule is set.
	 */
	function test_vb_insert_future_draft() {
		$future_date = strtotime( '+1 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'draft',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;
		// dmp( _get_cron_array() );
		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( $post['post_content'], $out->post_content );
		$this->assertSame( $post['post_title'], $out->post_title );
		$this->assertSame( 'draft', $out->post_status );
		$this->assertEquals( $post['post_author'], $out->post_author );
		$this->assertSame( $post['post_date'], $out->post_date );

		// There should be a publish_future_post hook scheduled on the future date.
		$this->assertFalse( $this->_next_schedule_for_post( 'publish_future_post', $id ) );

	}

	/**
	 * Insert a future post, then edit and change it to draft, and make sure cron gets it right.
	 */
	function test_vb_insert_future_change_to_draft() {
		$future_date_1 = strtotime( '+1 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date_1 ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;

		// Fetch the post and make sure has the correct date and status.
		$out = get_post( $id );
		$this->assertSame( 'future', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->_next_schedule_for_post( 'publish_future_post', $id ) );

		// Now save it again with status set to draft.

		$post['ID']          = $id;
		$post['post_status'] = 'draft';
		wp_update_post( $post );

		// Fetch the post again and make sure it has the new post_date.
		$out = get_post( $id );
		$this->assertSame( 'draft', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// And the correct date on the cron job.
		$this->assertFalse( $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * Insert a future post, then edit and change the status, and make sure cron gets it right.
	 */
	function test_vb_insert_future_change_status() {
		$future_date_1 = strtotime( '+1 day' );

		$statuses = array( 'draft', 'static', 'object', 'attachment', 'inherit', 'pending' );

		foreach ( $statuses as $status ) {
			$post = array(
				'post_author'  => self::$editor_id,
				'post_status'  => 'publish',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
				'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date_1 ),
			);

			// Insert a post and make sure the ID is OK.
			$id               = wp_insert_post( $post );
			$this->post_ids[] = $id;

			// Fetch the post and make sure has the correct date and status.
			$out = get_post( $id );
			$this->assertSame( 'future', $out->post_status );
			$this->assertSame( $post['post_date'], $out->post_date );

			// Check that there's a publish_future_post job scheduled at the right time.
			$this->assertSame( $future_date_1, $this->_next_schedule_for_post( 'publish_future_post', $id ) );

			// Now save it again with status changed.

			$post['ID']          = $id;
			$post['post_status'] = $status;
			wp_update_post( $post );

			// Fetch the post again and make sure it has the new post_date.
			$out = get_post( $id );
			$this->assertSame( $status, $out->post_status );
			$this->assertSame( $post['post_date'], $out->post_date );

			// And the correct date on the cron job.
			$this->assertFalse( $this->_next_schedule_for_post( 'publish_future_post', $id ) );
		}
	}

	/**
	 * Insert a draft post with a future date, and make sure no cron schedule is set.
	 */
	function test_vb_insert_future_private() {
		$future_date = strtotime( '+1 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'private',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;
		// dmp( _get_cron_array() );
		$this->assertTrue( is_numeric( $id ) );
		$this->assertTrue( $id > 0 );

		// Fetch the post and make sure it matches.
		$out = get_post( $id );

		$this->assertSame( $post['post_content'], $out->post_content );
		$this->assertSame( $post['post_title'], $out->post_title );
		$this->assertSame( 'private', $out->post_status );
		$this->assertEquals( $post['post_author'], $out->post_author );
		$this->assertSame( $post['post_date'], $out->post_date );

		// There should be a publish_future_post hook scheduled on the future date.
		$this->assertFalse( $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * Insert a post with an invalid date, make sure it fails.
	 *
	 * @ticket 17180
	 */
	function test_vb_insert_invalid_date() {
		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'public',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => '2012-02-30 00:00:00',
		);

		// Test both return paths with or without WP_Error.
		$insert_post = wp_insert_post( $post, true );
		$this->assertWPError( $insert_post );
		$this->assertSame( 'invalid_date', $insert_post->get_error_code() );

		$insert_post = wp_insert_post( $post );
		$this->assertSame( 0, $insert_post );
	}

	/**
	 * Insert a future post, then edit and change it to private, and make sure cron gets it right.
	 */
	function test_vb_insert_future_change_to_private() {
		$future_date_1 = strtotime( '+1 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date_1 ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;

		// Fetch the post and make sure has the correct date and status.
		$out = get_post( $id );
		$this->assertSame( 'future', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date_1, $this->_next_schedule_for_post( 'publish_future_post', $id ) );

		// Now save it again with status set to draft.

		$post['ID']          = $id;
		$post['post_status'] = 'private';
		wp_update_post( $post );

		// Fetch the post again and make sure it has the new post_date.
		$out = get_post( $id );
		$this->assertSame( 'private', $out->post_status );
		$this->assertSame( $post['post_date'], $out->post_date );

		// And the correct date on the cron job.
		$this->assertFalse( $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * @ticket 5305
	 */
	public function test_wp_insert_post_should_not_allow_a_bare_numeric_slug_that_might_conflict_with_a_date_archive_when_generating_from_an_empty_post_title() {
		$this->set_permalink_structure( '/%postname%/' );

		$p = wp_insert_post(
			array(
				'post_title'   => '',
				'post_content' => 'test',
				'post_status'  => 'publish',
				'post_type'    => 'post',
			)
		);

		$post = get_post( $p );

		$this->set_permalink_structure();

		$this->assertSame( "$p-2", $post->post_name );
	}

	/**
	 * @ticket 5305
	 * @ticket 33392
	 */
	public function test_wp_insert_post_should_invalidate_post_cache_before_generating_guid_when_post_name_is_empty_and_is_generated_from_the_post_ID() {
		register_post_type( 'wptests_pt' );

		$p = wp_insert_post(
			array(
				'post_title'  => '',
				'post_type'   => 'wptests_pt',
				'post_status' => 'publish',
			)
		);

		$post = get_post( $p );

		$this->assertContains( 'wptests_pt=' . $p, $post->guid );
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
	 */
	function test_delete_future_post_cron() {
		$future_date = strtotime( '+1 day' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => rand_str(),
			'post_date'    => strftime( '%Y-%m-%d %H:%M:%S', $future_date ),
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;

		// Check that there's a publish_future_post job scheduled at the right time.
		$this->assertSame( $future_date, $this->_next_schedule_for_post( 'publish_future_post', $id ) );

		// Now delete the post and make sure the cron entry is removed.
		wp_delete_post( $id );

		$this->assertFalse( $this->_next_schedule_for_post( 'publish_future_post', $id ) );
	}

	/**
	 * Bug: permalink doesn't work if post title is empty.
	 *
	 * Might only fail if the post ID is greater than four characters.
	 *
	 * @ticket 5305
	 */
	function test_permalink_without_title() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$post = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'publish',
			'post_content' => rand_str(),
			'post_title'   => '',
			'post_date'    => '2007-10-31 06:15:00',
		);

		// Insert a post and make sure the ID is OK.
		$id               = wp_insert_post( $post );
		$this->post_ids[] = $id;

		$plink = get_permalink( $id );

		// Permalink should include the post ID at the end.
		$this->assertSame( get_option( 'siteurl' ) . '/2007/10/31/' . $id . '/', $plink );
	}

	function test_wp_publish_post() {
		$draft_id = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$post = get_post( $draft_id );
		$this->assertSame( 'draft', $post->post_status );

		wp_publish_post( $draft_id );
		$post = get_post( $draft_id );

		$this->assertSame( 'publish', $post->post_status );
	}

	/**
	 * @ticket 22944
	 */
	function test_wp_insert_post_and_wp_publish_post_with_future_date() {
		$future_date = gmdate( 'Y-m-d H:i:s', time() + 10000000 );
		$post_id     = self::factory()->post->create(
			array(
				'post_status' => 'publish',
				'post_date'   => $future_date,
			)
		);

		$post = get_post( $post_id );
		$this->assertSame( 'future', $post->post_status );
		$this->assertSame( $future_date, $post->post_date );

		wp_publish_post( $post_id );
		$post = get_post( $post_id );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $future_date, $post->post_date );
	}

	/**
	 * @ticket 48145
	 */
	function test_wp_insert_post_should_default_to_publish_if_post_date_is_within_59_seconds_from_current_time() {
		$future_date = gmdate( 'Y-m-d H:i:s', time() + 59 );
		$post_id     = self::factory()->post->create(
			array(
				'post_date' => $future_date,
			)
		);

		$post = get_post( $post_id );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( $future_date, $post->post_date );
	}

	/**
	 * @ticket 22944
	 */
	function test_publish_post_with_content_filtering() {
		kses_remove_filters();

		$post_id = wp_insert_post( array( 'post_title' => '<script>Test</script>' ) );
		$post    = get_post( $post_id );
		$this->assertSame( '<script>Test</script>', $post->post_title );
		$this->assertSame( 'draft', $post->post_status );

		kses_init_filters();

		wp_update_post(
			array(
				'ID'          => $post->ID,
				'post_status' => 'publish',
			)
		);
		$post = get_post( $post->ID );
		$this->assertSame( 'Test', $post->post_title );

		kses_remove_filters();
	}

	/**
	 * @ticket 22944
	 */
	function test_wp_publish_post_and_avoid_content_filtering() {
		kses_remove_filters();

		$post_id = wp_insert_post( array( 'post_title' => '<script>Test</script>' ) );
		$post    = get_post( $post_id );
		$this->assertSame( '<script>Test</script>', $post->post_title );
		$this->assertSame( 'draft', $post->post_status );

		kses_init_filters();

		wp_publish_post( $post->ID );
		$post = get_post( $post->ID );
		$this->assertSame( '<script>Test</script>', $post->post_title );

		kses_remove_filters();
	}

	/**
	 * @ticket 23708
	 */
	function test_get_post_ancestors_within_loop() {
		global $post;
		$parent_id = self::factory()->post->create();
		$post      = self::factory()->post->create_and_get( array( 'post_parent' => $parent_id ) );
		$this->assertSame( array( $parent_id ), get_post_ancestors( 0 ) );
	}

	/**
	 * @ticket 23474
	 */
	function test_update_invalid_post_id() {
		$post_id = self::factory()->post->create( array( 'post_name' => 'get-page-uri-post-name' ) );
		$post    = get_post( $post_id, ARRAY_A );

		$post['ID'] = 123456789;

		$this->assertSame( 0, wp_insert_post( $post ) );
		$this->assertSame( 0, wp_update_post( $post ) );

		$this->assertInstanceOf( 'WP_Error', wp_insert_post( $post, true ) );
		$this->assertInstanceOf( 'WP_Error', wp_update_post( $post, true ) );

	}

	function test_parse_post_content_single_page() {
		global $multipage, $pages, $numpages;
		$post_id = self::factory()->post->create( array( 'post_content' => 'Page 0' ) );
		$post    = get_post( $post_id );
		setup_postdata( $post );
		$this->assertSame( 0, $multipage );
		$this->assertCount( 1, $pages );
		$this->assertSame( 1, $numpages );
		$this->assertSame( array( 'Page 0' ), $pages );
	}

	function test_parse_post_content_multi_page() {
		global $multipage, $pages, $numpages;
		$post_id = self::factory()->post->create( array( 'post_content' => 'Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3' ) );
		$post    = get_post( $post_id );
		setup_postdata( $post );
		$this->assertSame( 1, $multipage );
		$this->assertCount( 4, $pages );
		$this->assertSame( 4, $numpages );
		$this->assertSame( array( 'Page 0', 'Page 1', 'Page 2', 'Page 3' ), $pages );
	}

	function test_parse_post_content_remaining_single_page() {
		global $multipage, $pages, $numpages;
		$post_id = self::factory()->post->create( array( 'post_content' => 'Page 0' ) );
		$post    = get_post( $post_id );
		setup_postdata( $post );
		$this->assertSame( 0, $multipage );
		$this->assertCount( 1, $pages );
		$this->assertSame( 1, $numpages );
		$this->assertSame( array( 'Page 0' ), $pages );
	}

	function test_parse_post_content_remaining_multi_page() {
		global $multipage, $pages, $numpages;
		$post_id = self::factory()->post->create( array( 'post_content' => 'Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3' ) );
		$post    = get_post( $post_id );
		setup_postdata( $post );
		$this->assertSame( 1, $multipage );
		$this->assertCount( 4, $pages );
		$this->assertSame( 4, $numpages );
		$this->assertSame( array( 'Page 0', 'Page 1', 'Page 2', 'Page 3' ), $pages );
	}

	/**
	 * @ticket 16746
	 */
	function test_parse_post_content_starting_with_nextpage() {
		global $multipage, $pages, $numpages;
		$post_id = self::factory()->post->create( array( 'post_content' => '<!--nextpage-->Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3' ) );
		$post    = get_post( $post_id );
		setup_postdata( $post );
		$this->assertSame( 1, $multipage );
		$this->assertCount( 4, $pages );
		$this->assertSame( 4, $numpages );
		$this->assertSame( array( 'Page 0', 'Page 1', 'Page 2', 'Page 3' ), $pages );
	}

	/**
	 * @ticket 16746
	 */
	function test_parse_post_content_starting_with_nextpage_multi() {
		global $multipage, $pages, $numpages;
		$post_id = self::factory()->post->create( array( 'post_content' => '<!--nextpage-->Page 0' ) );
		$post    = get_post( $post_id );
		setup_postdata( $post );
		$this->assertSame( 0, $multipage );
		$this->assertCount( 1, $pages );
		$this->assertSame( 1, $numpages );
		$this->assertSame( array( 'Page 0' ), $pages );
	}

	/**
	 * @ticket 19373
	 */
	function test_insert_programmatic_sanitized() {
		$this->_unset_current_user();

		register_taxonomy( 'test_tax', 'post' );

		$title          = rand_str();
		$post_data      = array(
			'post_author'  => self::$editor_id,
			'post_status'  => 'public',
			'post_content' => rand_str(),
			'post_title'   => $title,
			'tax_input'    => array(
				'test_tax' => array( 'term', 'term2', 'term3' ),
			),
		);
		$insert_post_id = wp_insert_post( $post_data, true, true );
		$this->assertTrue( ( is_int( $insert_post_id ) && $insert_post_id > 0 ) );

		$post = get_post( $insert_post_id );
		$this->assertEquals( $post->post_author, self::$editor_id );
		$this->assertSame( $post->post_title, $title );
	}

	/**
	 * @ticket 24803
	 */
	function test_wp_count_posts() {
		$post_type = rand_str( 20 );
		register_post_type( $post_type );
		self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_author' => self::$editor_id,
			)
		);
		$count = wp_count_posts( $post_type, 'readable' );
		$this->assertEquals( 1, $count->publish );
		_unregister_post_type( $post_type );
		$this->assertEquals( new stdClass, wp_count_posts( $post_type, 'readable' ) );
	}

	function test_wp_count_posts_filtered() {
		$post_type = rand_str( 20 );
		register_post_type( $post_type );
		self::factory()->post->create_many(
			3,
			array(
				'post_type'   => $post_type,
				'post_author' => self::$editor_id,
			)
		);
		$count1 = wp_count_posts( $post_type, 'readable' );
		$this->assertEquals( 3, $count1->publish );
		add_filter( 'wp_count_posts', array( $this, 'filter_wp_count_posts' ) );

		$count2 = wp_count_posts( $post_type, 'readable' );
		$this->assertEquals( 2, $count2->publish );

		remove_filter( 'wp_count_posts', array( $this, 'filter_wp_count_posts' ) );
	}

	function filter_wp_count_posts( $counts ) {
		$counts->publish = 2;
		return $counts;
	}

	function test_wp_count_posts_insert_invalidation() {
		$post_ids       = self::factory()->post->create_many( 3 );
		$initial_counts = wp_count_posts();

		$key                  = array_rand( $post_ids );
		$_post                = get_post( $post_ids[ $key ], ARRAY_A );
		$_post['post_status'] = 'draft';
		wp_insert_post( $_post );
		$post = get_post( $post_ids[ $key ] );
		$this->assertSame( 'draft', $post->post_status );
		$this->assertNotEquals( 'publish', $post->post_status );

		$after_draft_counts = wp_count_posts();
		$this->assertEquals( 1, $after_draft_counts->draft );
		$this->assertEquals( 2, $after_draft_counts->publish );
		$this->assertNotEquals( $initial_counts->publish, $after_draft_counts->publish );
	}

	function test_wp_count_posts_trash_invalidation() {
		$post_ids       = self::factory()->post->create_many( 3 );
		$initial_counts = wp_count_posts();

		$key = array_rand( $post_ids );

		wp_trash_post( $post_ids[ $key ] );

		$post = get_post( $post_ids[ $key ] );
		$this->assertSame( 'trash', $post->post_status );
		$this->assertNotEquals( 'publish', $post->post_status );

		$after_trash_counts = wp_count_posts();
		$this->assertEquals( 1, $after_trash_counts->trash );
		$this->assertEquals( 2, $after_trash_counts->publish );
		$this->assertNotEquals( $initial_counts->publish, $after_trash_counts->publish );
	}

	/**
	 * @ticket 49685
	 */
	function test_wp_count_posts_status_changes_visible() {
		self::factory()->post->create_many( 3 );

		// Trigger a cache.
		wp_count_posts();

		register_post_status( 'test' );

		$counts = wp_count_posts();
		$this->assertTrue( isset( $counts->test ) );
		$this->assertSame( 0, $counts->test );
	}

	/**
	 * @ticket 25566
	 */
	function test_wp_tag_cloud_link_with_post_type() {
		$post_type = 'new_post_type';
		$tax       = 'new_tag';
		register_post_type( $post_type, array( 'taxonomies' => array( 'post_tag', $tax ) ) );
		register_taxonomy( $tax, $post_type );

		$post = self::factory()->post->create( array( 'post_type' => $post_type ) );
		wp_set_object_terms( $post, rand_str(), $tax );

		$wp_tag_cloud = wp_tag_cloud(
			array(
				'post_type' => $post_type,
				'taxonomy'  => $tax,
				'echo'      => false,
				'link'      => 'edit',
			)
		);

		preg_match_all( '|href="([^"]+)"|', $wp_tag_cloud, $matches );
		$this->assertSame( 1, count( $matches[1] ) );

		$terms = get_terms( $tax );
		$term  = reset( $terms );

		foreach ( $matches[1] as $url ) {
			$this->assertContains( 'tag_ID=' . $term->term_id, $url );
			$this->assertContains( 'post_type=new_post_type', $url );
		}
	}

	/**
	 * @ticket 21212
	 */
	function test_utf8mb3_post_saves_with_emoji() {
		global $wpdb;

		if ( 'utf8' !== $wpdb->get_col_charset( $wpdb->posts, 'post_title' ) ) {
			$this->markTestSkipped( 'This test is only useful with the utf8 character set' );
		}

		require_once ABSPATH . '/wp-admin/includes/post.php';

		$post_id = self::factory()->post->create();

		$data = array(
			'post_ID'      => $post_id,
			'post_title'   => "foo\xf0\x9f\x98\x88bar",
			'post_content' => "foo\xf0\x9f\x98\x8ebaz",
			'post_excerpt' => "foo\xf0\x9f\x98\x90bat",
		);

		$expected = array(
			'post_title'   => 'foo&#x1f608;bar',
			'post_content' => 'foo&#x1f60e;baz',
			'post_excerpt' => 'foo&#x1f610;bat',
		);

		edit_post( $data );

		$post = get_post( $post_id );

		foreach ( $expected as $field => $value ) {
			$this->assertSame( $value, $post->$field );
		}
	}

	/**
	 * @ticket 31168
	 */
	function test_wp_insert_post_default_comment_ping_status_open() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$editor_id,
				'post_status'  => 'public',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( 'open', $post->comment_status );
		$this->assertSame( 'open', $post->ping_status );
	}

	/**
	 * @ticket 31168
	 */
	function test_wp_insert_post_page_default_comment_ping_status_closed() {
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$editor_id,
				'post_status'  => 'public',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
				'post_type'    => 'page',
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( 'closed', $post->ping_status );
	}

	/**
	 * @ticket 31168
	 */
	function test_wp_insert_post_cpt_default_comment_ping_status_open() {
		$post_type = rand_str( 20 );
		register_post_type( $post_type, array( 'supports' => array( 'comments', 'trackbacks' ) ) );
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$editor_id,
				'post_status'  => 'public',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
				'post_type'    => $post_type,
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( 'open', $post->comment_status );
		$this->assertSame( 'open', $post->ping_status );
		_unregister_post_type( $post_type );
	}

	/**
	 * @ticket 31168
	 */
	function test_wp_insert_post_cpt_default_comment_ping_status_closed() {
		$post_type = rand_str( 20 );
		register_post_type( $post_type );
		$post_id = self::factory()->post->create(
			array(
				'post_author'  => self::$editor_id,
				'post_status'  => 'public',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
				'post_type'    => $post_type,
			)
		);
		$post    = get_post( $post_id );

		$this->assertSame( 'closed', $post->comment_status );
		$this->assertSame( 'closed', $post->ping_status );
		_unregister_post_type( $post_type );
	}

	/**
	 * If a post is sticky and is updated by a user that does not have the publish_post capability,
	 * it should _stay_ sticky.
	 *
	 * @ticket 24153
	 */
	function test_user_without_publish_cannot_affect_sticky() {
		wp_set_current_user( self::$grammarian_id );

		// Sanity check.
		$this->assertFalse( current_user_can( 'publish_posts' ) );
		$this->assertTrue( current_user_can( 'edit_others_posts' ) );
		$this->assertTrue( current_user_can( 'edit_published_posts' ) );

		// Create a sticky post.
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'Will be changed',
				'post_content' => 'Will be changed',
			)
		);
		stick_post( $post->ID );

		// Sanity check.
		$this->assertTrue( is_sticky( $post->ID ) );

		// Edit the post.
		$post->post_title   = 'Updated';
		$post->post_content = 'Updated';
		wp_update_post( $post );

		// Make sure it's still sticky.
		$saved_post = get_post( $post->ID );
		$this->assertTrue( is_sticky( $saved_post->ID ) );
		$this->assertSame( 'Updated', $saved_post->post_title );
		$this->assertSame( 'Updated', $saved_post->post_content );
	}

	/**
	 * If the `edit_post()` method is invoked by a user without publish_posts permission,
	 * the sticky status of the post should not be changed.
	 *
	 * @ticket 24153
	 */
	function test_user_without_publish_cannot_affect_sticky_with_edit_post() {
		// Create a sticky post.
		$post = self::factory()->post->create_and_get(
			array(
				'post_title'   => 'Will be changed',
				'post_content' => 'Will be changed',
			)
		);
		stick_post( $post->ID );

		// Sanity check.
		$this->assertTrue( is_sticky( $post->ID ) );

		wp_set_current_user( self::$grammarian_id );

		// Sanity check.
		$this->assertFalse( current_user_can( 'publish_posts' ) );
		$this->assertTrue( current_user_can( 'edit_others_posts' ) );
		$this->assertTrue( current_user_can( 'edit_published_posts' ) );

		// Edit the post - the key 'sticky' is intentionally unset.
		$data = array(
			'post_ID'      => $post->ID,
			'post_title'   => 'Updated',
			'post_content' => 'Updated',
		);
		edit_post( $data );

		// Make sure it's still sticky.
		$saved_post = get_post( $post->ID );
		$this->assertTrue( is_sticky( $saved_post->ID ) );
		$this->assertSame( 'Updated', $saved_post->post_title );
		$this->assertSame( 'Updated', $saved_post->post_content );
	}

	/**
	 * Test that hooks are fired when post gets stuck and unstuck.
	 *
	 * @ticket 35600
	 */
	function test_hooks_fire_when_post_gets_stuck_and_unstuck() {
		$post_id = self::factory()->post->create();
		$a1      = new MockAction();
		$a2      = new MockAction();

		$this->assertFalse( is_sticky( $post_id ) );

		add_action( 'post_stuck', array( $a1, 'action' ) );
		add_action( 'post_unstuck', array( $a2, 'action' ) );

		stick_post( $post_id );
		$this->assertTrue( is_sticky( $post_id ) );
		unstick_post( $post_id );
		$this->assertFalse( is_sticky( $post_id ) );

		remove_action( 'post_stuck', array( $a1, 'action' ) );
		remove_action( 'post_unstuck', array( $a2, 'action' ) );

		$this->assertSame( 1, $a1->get_call_count() );
		$this->assertSame( 1, $a2->get_call_count() );
	}

	/**
	 * If a post is updated without providing a post_name param,
	 * a new slug should not be generated.
	 *
	 * @ticket 34865
	 */
	function test_post_updates_without_slug_provided() {
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
		$post_id = self::factory()->post->create( array( 'post_author' => null ) );

		$this->assertEquals( self::$editor_id, get_post( $post_id )->post_author );
	}

	/**
	 * @ticket 15946
	 */
	function test_wp_insert_post_should_respect_post_date_gmt() {
		$post = array(
			'post_author'   => self::$editor_id,
			'post_status'   => 'publish',
			'post_content'  => rand_str(),
			'post_title'    => rand_str(),
			'post_date_gmt' => '2014-01-01 12:00:00',
		);

		// Insert a post and make sure the ID is OK.
		$id = wp_insert_post( $post );

		$out = get_post( $id );

		$this->assertSame( $post['post_content'], $out->post_content );
		$this->assertSame( $post['post_title'], $out->post_title );
		$this->assertEquals( $post['post_author'], $out->post_author );
		$this->assertSame( get_date_from_gmt( $post['post_date_gmt'] ), $out->post_date );
		$this->assertSame( $post['post_date_gmt'], $out->post_date_gmt );
	}

	function test_wp_delete_post_reassign_hierarchical_post_type() {
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
	 * Test ensuring that the post_name (UUID) is preserved when wp_insert_post()/wp_update_post() is called.
	 *
	 * @see _wp_customize_changeset_filter_insert_post_data()
	 * @ticket 30937
	 */
	function test_wp_insert_post_for_customize_changeset_should_not_drop_post_name() {

		$this->assertSame( 10, has_filter( 'wp_insert_post_data', '_wp_customize_changeset_filter_insert_post_data' ) );

		$changeset_data = array(
			'blogname' => array(
				'value' => 'Hello World',
			),
		);

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'contributor' ) ) );

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
	 * Test ensuring that the post_slug can be filtered with a custom value short circuiting the built in
	 * function that tries to create a unique name based on the post name.
	 *
	 * @see wp_unique_post_slug()
	 * @ticket 21112
	 */
	function test_pre_wp_unique_post_slug_filter() {
		add_filter( 'pre_wp_unique_post_slug', array( $this, 'filter_pre_wp_unique_post_slug' ), 10, 6 );

		$post_id = $this->factory->post->create(
			array(
				'title'       => 'An example',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		$post    = get_post( $post_id );
		$this->assertSame( 'override-slug-' . $post->post_type, $post->post_name );

		remove_filter( 'pre_wp_unique_post_slug', array( $this, 'filter_pre_wp_unique_post_slug' ), 10, 6 );
	}

	function filter_pre_wp_unique_post_slug( $default, $slug, $post_ID, $post_status, $post_type, $post_parent ) {
		return 'override-slug-' . $post_type;
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
		self::assertEqualsWithDelta( strtotime( gmdate( 'Y-m-d H:i:s' ) ), strtotime( $post->post_date_gmt ), 2, 'The dates should be equal' );
	}

	/**
	 * Test ensuring that wp_update_post() does not unintentionally modify post tags
	 * if the post has several tags with the same name but different slugs.
	 *
	 * Tags should only be modified if 'tags_input' parameter was explicitly provided,
	 * and is different from the existing tags.
	 *
	 * @ticket 45121
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
}
