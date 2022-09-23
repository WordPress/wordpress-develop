<?php

/**
 * test wp-includes/post.php
 *
 * @group post
 */
class Tests_Post extends WP_UnitTestCase {
	protected static $editor_id;
	protected static $grammarian_id;

	private $post_ids = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
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

	public function test_parse_post_content_single_page() {
		global $multipage, $pages, $numpages;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'Page 0',
			)
		);
		$post    = get_post( $post_id );
		setup_postdata( $post );

		$this->assertSame( 0, $multipage );
		$this->assertCount( 1, $pages );
		$this->assertSame( 1, $numpages );
		$this->assertSame( array( 'Page 0' ), $pages );
	}

	public function test_parse_post_content_multi_page() {
		global $multipage, $pages, $numpages;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3',
			)
		);
		$post    = get_post( $post_id );
		setup_postdata( $post );

		$this->assertSame( 1, $multipage );
		$this->assertCount( 4, $pages );
		$this->assertSame( 4, $numpages );
		$this->assertSame( array( 'Page 0', 'Page 1', 'Page 2', 'Page 3' ), $pages );
	}

	public function test_parse_post_content_remaining_single_page() {
		global $multipage, $pages, $numpages;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'Page 0',
			)
		);
		$post    = get_post( $post_id );
		setup_postdata( $post );

		$this->assertSame( 0, $multipage );
		$this->assertCount( 1, $pages );
		$this->assertSame( 1, $numpages );
		$this->assertSame( array( 'Page 0' ), $pages );
	}

	public function test_parse_post_content_remaining_multi_page() {
		global $multipage, $pages, $numpages;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => 'Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3',
			)
		);
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
	public function test_parse_post_content_starting_with_nextpage() {
		global $multipage, $pages, $numpages;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!--nextpage-->Page 0<!--nextpage-->Page 1<!--nextpage-->Page 2<!--nextpage-->Page 3',
			)
		);
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
	public function test_parse_post_content_starting_with_nextpage_multi() {
		global $multipage, $pages, $numpages;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<!--nextpage-->Page 0',
			)
		);
		$post    = get_post( $post_id );
		setup_postdata( $post );

		$this->assertSame( 0, $multipage );
		$this->assertCount( 1, $pages );
		$this->assertSame( 1, $numpages );
		$this->assertSame( array( 'Page 0' ), $pages );
	}

	/**
	 * @ticket 24803
	 */
	public function test_wp_count_posts() {
		$post_type = rand_str( 20 );
		register_post_type( $post_type );

		self::factory()->post->create(
			array(
				'post_type' => $post_type,
			)
		);

		$count = wp_count_posts( $post_type, 'readable' );
		$this->assertEquals( 1, $count->publish );

		_unregister_post_type( $post_type );
		$count = wp_count_posts( $post_type, 'readable' );
		$this->assertEquals( new stdClass, $count );
	}

	public function test_wp_count_posts_filtered() {
		$post_type = rand_str( 20 );
		register_post_type( $post_type );

		self::factory()->post->create_many(
			3,
			array(
				'post_type' => $post_type,
			)
		);

		$count1 = wp_count_posts( $post_type, 'readable' );
		$this->assertEquals( 3, $count1->publish );

		add_filter( 'wp_count_posts', array( $this, 'filter_wp_count_posts' ) );
		$count2 = wp_count_posts( $post_type, 'readable' );
		remove_filter( 'wp_count_posts', array( $this, 'filter_wp_count_posts' ) );
		$this->assertEquals( 2, $count2->publish );
	}

	public function filter_wp_count_posts( $counts ) {
		$counts->publish = 2;
		return $counts;
	}

	public function test_wp_count_posts_insert_invalidation() {
		$post_ids       = self::factory()->post->create_many( 3 );
		$initial_counts = wp_count_posts();

		$key   = array_rand( $post_ids );
		$_post = get_post( $post_ids[ $key ], ARRAY_A );

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

	public function test_wp_count_posts_trash_invalidation() {
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
	public function test_wp_count_posts_status_changes_visible() {
		self::factory()->post->create_many( 3 );

		// Trigger a cache.
		wp_count_posts();

		register_post_status( 'test' );

		$counts = wp_count_posts();
		$this->assertObjectHasAttribute( 'test', $counts );
		$this->assertSame( 0, $counts->test );
	}

	/**
	 * @ticket 25566
	 */
	public function test_wp_tag_cloud_link_with_post_type() {
		$post_type = 'new_post_type';
		$tax       = 'new_tag';
		register_post_type( $post_type, array( 'taxonomies' => array( 'post_tag', $tax ) ) );
		register_taxonomy( $tax, $post_type );

		$post = self::factory()->post->create( array( 'post_type' => $post_type ) );
		wp_set_object_terms( $post, 'foo', $tax );

		wp_set_current_user( self::$editor_id );

		$wp_tag_cloud = wp_tag_cloud(
			array(
				'post_type' => $post_type,
				'taxonomy'  => $tax,
				'echo'      => false,
				'link'      => 'edit',
			)
		);

		preg_match_all( '|href="([^"]+)"|', $wp_tag_cloud, $matches );
		$this->assertCount( 1, $matches[1] );

		$terms = get_terms( $tax );
		$term  = reset( $terms );

		foreach ( $matches[1] as $url ) {
			$this->assertStringContainsString( 'tag_ID=' . $term->term_id, $url );
			$this->assertStringContainsString( 'post_type=new_post_type', $url );
		}
	}

	/**
	 * @ticket 21212
	 */
	public function test_utf8mb3_post_saves_with_emoji() {
		global $wpdb;

		if ( 'utf8' !== $wpdb->get_col_charset( $wpdb->posts, 'post_title' ) ) {
			$this->markTestSkipped( 'This test is only useful with the utf8 character set.' );
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

		wp_set_current_user( self::$editor_id );

		edit_post( $data );

		$post = get_post( $post_id );

		foreach ( $expected as $field => $value ) {
			$this->assertSame( $value, $post->$field );
		}
	}

	/**
	 * If a sticky post is updated via `wp_update_post()` by a user
	 * without the `publish_posts` capability, it should stay sticky.
	 *
	 * @ticket 24153
	 */
	public function test_user_without_publish_posts_cannot_affect_sticky() {
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
	 * If a sticky post is updated via `edit_post()` by a user
	 * without the `publish_posts` capability, it should stay sticky.
	 *
	 * @ticket 24153
	 */
	public function test_user_without_publish_posts_cannot_affect_sticky_with_edit_post() {
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
	public function test_hooks_fire_when_post_gets_stuck_and_unstuck() {
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
	 * Test ensuring that the post_slug can be filtered with a custom value short circuiting the built in
	 * function that tries to create a unique name based on the post name.
	 *
	 * @see wp_unique_post_slug()
	 * @ticket 21112
	 */
	public function test_pre_wp_unique_post_slug_filter() {
		add_filter( 'pre_wp_unique_post_slug', array( $this, 'filter_pre_wp_unique_post_slug' ), 10, 6 );

		$post_id = self::factory()->post->create(
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

	public function filter_pre_wp_unique_post_slug( $default, $slug, $post_ID, $post_status, $post_type, $post_parent ) {
		return 'override-slug-' . $post_type;
	}

	/**
	 * @ticket 52187
	 */
	public function test_wp_resolve_post_date() {
		$post_date     = '2020-12-28 11:26:35';
		$post_date_gmt = '2020-12-29 10:11:45';
		$invalid_date  = '2020-12-41 14:15:27';

		$resolved_post_date = wp_resolve_post_date();
		$this->assertEqualsWithDelta(
			strtotime( gmdate( 'Y-m-d H:i:s' ) ),
			strtotime( $resolved_post_date ),
			2,
			'The dates should be equal'
		);

		$resolved_post_date = wp_resolve_post_date( '', $post_date_gmt );
		$this->assertSame( get_date_from_gmt( $post_date_gmt ), $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( '', $invalid_date );
		$this->assertSame( '1970-01-01 00:00:00', $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( $post_date );
		$this->assertSame( $post_date, $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( $post_date, $post_date_gmt );
		$this->assertSame( $post_date, $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( $post_date, $invalid_date );
		$this->assertSame( $post_date, $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( $invalid_date );
		$this->assertFalse( $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( $invalid_date, $post_date_gmt );
		$this->assertFalse( $resolved_post_date );

		$resolved_post_date = wp_resolve_post_date( $invalid_date, $invalid_date );
		$this->assertFalse( $resolved_post_date );
	}

	/**
	 * Ensure sticking a post updates the `sticky_posts` option.
	 *
	 * @covers ::stick_post
	 */
	public function test_stick_post_updates_option() {
		stick_post( 1 );
		$this->assertSameSets( array( 1 ), get_option( 'sticky_posts' ) );

		stick_post( 2 );
		$this->assertSameSets( array( 1, 2 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Ensure sticking a post does not duplicate post IDs in the option.
	 *
	 * @ticket 52007
	 * @covers ::stick_post
	 * @dataProvider data_stick_post_does_not_duplicate_post_ids
	 *
	 * @param mixed $stick Value to pass to stick_post().
	 */
	public function test_stick_post_does_not_duplicate_post_ids( $stick ) {
		update_option( 'sticky_posts', array( 1, 2 ) );

		stick_post( $stick );
		$this->assertSameSets( array( 1, 2 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Data provider for test_stick_post_does_not_duplicate_post_ids().
	 *
	 * @return array[] {
	 *     Arguments passed to test.
	 *
	 *     @type mixed $stick Value to pass to stick_post().
	 * }
	 */
	public function data_stick_post_does_not_duplicate_post_ids() {
		return array(
			array( 1 ),
			array( '1' ),
			array( 2.0 ),
		);
	}

	/**
	 * Ensures sticking a post succeeds after deleting the 'sticky_posts' option.
	 *
	 * @ticket 52007
	 * @ticket 55176
	 * @covers ::stick_post
	 */
	public function test_stick_post_after_delete_sticky_posts_option() {
		delete_option( 'sticky_posts' );

		stick_post( 1 );
		$this->assertSameSets( array( 1 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Ensures sticking works with an unexpected option value.
	 *
	 * @ticket 52007
	 * @ticket 55176
	 * @covers ::stick_post
	 * @dataProvider data_stick_post_with_unexpected_sticky_posts_option
	 *
	 * @param mixed $starting_option Starting value for sticky_posts option.
	 */
	public function test_stick_post_with_unexpected_sticky_posts_option( $starting_option ) {
		update_option( 'sticky_posts', $starting_option );

		stick_post( 1 );
		$this->assertSameSets( array( 1 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_stick_post_with_unexpected_sticky_posts_option() {
		return array(
			'false'     => array( false ),
			'a string'  => array( 'string' ),
			'1 int'     => array( 1 ),
			'null'      => array( null ),
			'true'      => array( true ),
			'an object' => array( new stdClass ),
		);
	}

	/**
	 * Ensure sticking a post removes other duplicate post IDs from the option.
	 *
	 * @ticket 52007
	 * @covers ::stick_post
	 *
	 * @param mixed $stick Value to pass to stick_post().
	 */
	public function test_stick_post_removes_duplicate_post_ids_when_adding_new_value() {
		update_option( 'sticky_posts', array( 1, 1, 2, 2 ) );

		stick_post( 3 );
		$this->assertSameSets( array( 1, 2, 3 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Ensure unsticking a post updates the `sticky_posts` option.
	 *
	 * @covers ::unstick_post
	 */
	public function test_unstick_post_updates_option() {
		update_option( 'sticky_posts', array( 1 ) );
		unstick_post( 1 );
		$this->assertEmpty( get_option( 'sticky_posts' ) );

		update_option( 'sticky_posts', array( 1, 2 ) );
		unstick_post( 1 );
		$this->assertSameSets( array( 2 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Ensure unsticking a post removes duplicate post IDs from the option.
	 *
	 * @ticket 52007
	 * @covers ::unstick_post
	 *
	 * @dataProvider data_unstick_post_removes_duplicate_post_ids
	 *
	 * @param array $starting_option Original value of `sticky_posts` option.
	 * @param mixed $unstick         Parameter passed to `unstick_post()`
	 * @param array $expected
	 */
	public function test_unstick_post_removes_duplicate_post_ids( $starting_option, $unstick, $expected ) {
		update_option( 'sticky_posts', $starting_option );
		unstick_post( $unstick );
		$this->assertSameSets( $expected, get_option( 'sticky_posts' ) );
	}

	/**
	 * Data provider for test_unstick_post_removes_duplicate_post_ids().
	 *
	 * @return array[] {
	 *     Arguments passed to test.
	 *
	 *     @type array $starting_option Original value of `sticky_posts` option.
	 *     @type mixed $unstick         Parameter passed to `unstick_post()`
	 *     @type array $expected
	 * }
	 */
	public function data_unstick_post_removes_duplicate_post_ids() {
		return array(
			array(
				array( 1, 1 ),
				1,
				array(),
			),
			array(
				array( 1, 1 ),
				'1',
				array(),
			),
			array(
				array( 1, 2, 1 ),
				1,
				array( 2 ),
			),
			array(
				array( 1, 2, 1 ),
				2,
				array( 1 ),
			),
			array(
				array( 1, 2, 1 ),
				2.0,
				array( 1 ),
			),
		);
	}

	/**
	 * Ensure sticking a duplicate post does not update the `sticky_posts` option.
	 *
	 * @ticket 52007
	 * @covers ::stick_post
	 */
	public function test_stick_post_with_duplicate_post_id_does_not_update_option() {
		update_option( 'sticky_posts', array( 1, 2, 2 ) );
		stick_post( 2 );
		$this->assertSameSets( array( 1, 2, 2 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Ensure unsticking a non-sticky post does not update the `sticky_posts` option.
	 *
	 * @ticket 52007
	 * @covers ::unstick_post
	 */
	public function test_unstick_post_with_non_sticky_post_id_does_not_update_option() {
		update_option( 'sticky_posts', array( 1, 2, 2 ) );
		unstick_post( 3 );
		$this->assertSameSets( array( 1, 2, 2 ), get_option( 'sticky_posts' ) );
	}

	/**
	 * Check if post supports block editor.
	 *
	 * @ticket 51819
	 * @covers ::use_block_editor_for_post
	 */
	public function test_use_block_editor_for_post() {
		$this->assertFalse( use_block_editor_for_post( -1 ) );
		$bogus_post_id = self::factory()->post->create(
			array(
				'post_type' => 'bogus',
			)
		);
		$this->assertFalse( use_block_editor_for_post( $bogus_post_id ) );

		register_post_type(
			'restless',
			array(
				'show_in_rest' => false,
			)
		);
		$restless_post_id = self::factory()->post->create(
			array(
				'post_type' => 'restless',
			)
		);
		$this->assertFalse( use_block_editor_for_post( $restless_post_id ) );

		$generic_post_id = self::factory()->post->create();

		add_filter( 'use_block_editor_for_post', '__return_false' );
		$this->assertFalse( use_block_editor_for_post( $generic_post_id ) );
		remove_filter( 'use_block_editor_for_post', '__return_false' );

		add_filter( 'use_block_editor_for_post', '__return_true' );
		$this->assertTrue( use_block_editor_for_post( $restless_post_id ) );
		remove_filter( 'use_block_editor_for_post', '__return_true' );
	}
}
