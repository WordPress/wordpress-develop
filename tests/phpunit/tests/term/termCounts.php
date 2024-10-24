<?php

/**
 * @group taxonomy
 */
class Tests_Term_termCount extends WP_UnitTestCase {

	/**
	 * Term ID for testing attachment counts.
	 *
	 * @var int
	 */
	public static $attachment_term;

	/**
	 * Post IDs of shared posts.
	 *
	 * @var int[]
	 */
	public static $post_ids;

	/**
	 * Array of tag IDs.
	 *
	 * @var int[]
	 */
	public static $tag_ids;

	/**
	 * Term ID for testing user counts.
	 *
	 * @var int
	 */
	public static $user_term;

	/**
	 * User ID for testing user counts.
	 *
	 * @var int
	 */
	public static $user_id;

	/**
	 * Create shared fixtures.
	 *
	 * @param WP_UnitTest_Factory $factory Test suite factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$statuses = array( 'publish', 'auto-draft', 'draft', 'private' );
		foreach ( $statuses as $status ) {
			self::$post_ids[ $status ] = $factory->post->create( array( 'post_status' => $status ) );
		}

		// Extra published post.
		self::$post_ids['publish_two'] = $factory->post->create( array( 'post_status' => 'publish' ) );

		self::$user_id = $factory->user->create( array( 'role' => 'author' ) );

		self::register_taxonomies();
		self::$attachment_term = $factory->term->create( array( 'taxonomy' => 'wp_test_tax_counts' ) );
		self::$user_term       = $factory->term->create( array( 'taxonomy' => 'wp_test_user_tax_counts' ) );
		self::$tag_ids         = $factory->term->create_many( 5 );
	}

	public function set_up() {
		parent::set_up();
		self::register_taxonomies();
	}

	/**
	 * Register taxonomies used by tests.
	 *
	 * This is called both before class and before each test as the global is
	 * reset in each test's tearDown.
	 */
	public static function register_taxonomies() {
		register_taxonomy( 'wp_test_tax_counts', array( 'post', 'attachment' ) );
		register_taxonomy( 'wp_test_user_tax_counts', 'user' );
	}

	/**
	 * Term counts are not double incremented when post created.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_count_changes_for_post_statuses
	 * @ticket 40351
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_post_statuses( $post_status, $change ) {
		$term_count = get_term( get_option( 'default_category' ) )->count;
		// Do not use shared fixture for this test as it relies on a new post.
		$post_id = self::factory()->post->create( array( 'post_status' => $post_status ) );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	public function data_term_count_changes_for_post_statuses() {
		return array(
			// 0. Published post
			array( 'publish', 1 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @covers ::wp_publish_post
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_counts_incremented_on_publish
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish( $original_post_status, $change ) {
		$post_id    = self::$post_ids[ $original_post_status ];
		$term_count = get_term( get_option( 'default_category' ) )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	public function data_term_counts_incremented_on_publish() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 1 ),
			// 2. Draft
			array( 'draft', 1 ),
			// 3. Private post
			array( 'private', 1 ),
		);
	}

	/**
	 * Test post status transition update term counts correctly.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_count_transitions_update_term_counts
	 * @ticket 40351
	 *
	 * @param string $original_post_status Post status upon create.
	 * @param string $new_post_status      Post status after update.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_count_transitions_update_term_counts( $original_post_status, $new_post_status, $change ) {
		$post_id    = self::$post_ids[ $original_post_status ];
		$term_count = get_term( get_option( 'default_category' ) )->count;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_post_status,
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( get_option( 'default_category' ) )->count );
	}

	/**
	 * Data provider for test_term_count_transitions_update_term_counts.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status upon create.
	 *     @type string $new_post_status      Post status after update.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	public function data_term_count_transitions_update_term_counts() {
		return array(
			// 0. Draft -> published post
			array( 'draft', 'publish', 1 ),
			// 1. Auto draft -> published post
			array( 'auto-draft', 'publish', 1 ),
			// 2. Private -> published post
			array( 'private', 'publish', 1 ),
			// 3. Published -> published post
			array( 'publish', 'publish', 0 ),

			// 4. Draft -> private post
			array( 'draft', 'private', 0 ),
			// 5. Auto draft -> private post
			array( 'auto-draft', 'private', 0 ),
			// 6. Private -> private post
			array( 'private', 'private', 0 ),
			// 7. Published -> private post
			array( 'publish', 'private', -1 ),

			// 8. Draft -> draft post
			array( 'draft', 'draft', 0 ),
			// 9. Auto draft -> draft post
			array( 'auto-draft', 'draft', 0 ),
			// 10. Private -> draft post
			array( 'private', 'draft', 0 ),
			// 11. Published -> draft post
			array( 'publish', 'draft', -1 ),
		);
	}

	public function add_custom_status_to_counted_statuses( $statuses ) {
		array_push( $statuses, 'custom' );
		return $statuses;
	}

	/**
	 * Term counts incremented correctly when the `update_post_term_count_statuses` filter is used.
	 *
	 * @covers ::wp_update_term_count
	 * @dataProvider data_term_count_changes_for_update_post_term_count_statuses_filter
	 * @ticket 38843
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_update_post_term_count_statuses_filter( $post_status, $change ) {
		$term_count = get_term( self::$attachment_term )->count;

		add_filter( 'update_post_term_count_statuses', array( $this, 'add_custom_status_to_counted_statuses' ) );

		$post_id = self::factory()->post->create( array( 'post_status' => $post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );

		remove_filter( 'update_post_term_count_statuses', array( $this, 'add_custom_status_to_counted_statuses' ) );
	}

	/**
	 * Data provider for test_term_count_changes_for_update_post_term_count_statuses_filter.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	public function data_term_count_changes_for_update_post_term_count_statuses_filter() {
		return array(
			// 0. Published post
			array( 'publish', 2 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
			// 4. Custom post status
			array( 'custom', 2 ),
		);
	}

	/**
	 * Term counts are not double incremented when post created.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_count_changes_for_post_statuses_with_attachments
	 * @ticket 40351
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_post_statuses_with_attachments( $post_status, $change ) {
		$term_count = get_term( self::$attachment_term )->count;
		// Do not use shared fixture for this test as it relies on a new post.
		$post_id = self::factory()->post->create( array( 'post_status' => $post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	public function data_term_count_changes_for_post_statuses_with_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 2 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @covers ::wp_publish_post
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_counts_incremented_on_publish_with_attachments
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish_with_attachments( $original_post_status, $change ) {
		$post_id = self::$post_ids[ $original_post_status ];
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );
		$term_count = get_term( self::$attachment_term )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	public function data_term_counts_incremented_on_publish_with_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 2 ),
			// 2. Draft
			array( 'draft', 2 ),
			// 3. Private post
			array( 'private', 2 ),
		);
	}

	/**
	 * Test post status transition update term counts correctly.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_count_transitions_update_term_counts_with_attachments
	 * @ticket 40351
	 *
	 * @param string $original_post_status Post status upon create.
	 * @param string $new_post_status      Post status after update.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_count_transitions_update_term_counts_with_attachments( $original_post_status, $new_post_status, $change ) {
		$post_id = self::$post_ids[ $original_post_status ];
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		wp_add_object_terms( $attachment_id, self::$attachment_term, 'wp_test_tax_counts' );
		$term_count = get_term( self::$attachment_term )->count;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_post_status,
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_transitions_update_term_counts_with_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status upon create.
	 *     @type string $new_post_status      Post status after update.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	public function data_term_count_transitions_update_term_counts_with_attachments() {
		return array(
			// 0. Draft -> published post
			array( 'draft', 'publish', 2 ),
			// 1. Auto draft -> published post
			array( 'auto-draft', 'publish', 2 ),
			// 2. Private -> published post
			array( 'private', 'publish', 2 ),
			// 3. Published -> published post
			array( 'publish', 'publish', 0 ),

			// 4. Draft -> private post
			array( 'draft', 'private', 0 ),
			// 5. Auto draft -> private post
			array( 'auto-draft', 'private', 0 ),
			// 6. Private -> private post
			array( 'private', 'private', 0 ),
			// 7. Published -> private post
			array( 'publish', 'private', -2 ),

			// 8. Draft -> draft post
			array( 'draft', 'draft', 0 ),
			// 9. Auto draft -> draft post
			array( 'auto-draft', 'draft', 0 ),
			// 10. Private -> draft post
			array( 'private', 'draft', 0 ),
			// 11. Published -> draft post
			array( 'publish', 'draft', -2 ),
		);
	}

	/**
	 * Term counts are not double incremented when post created.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_count_changes_for_post_statuses_with_untermed_attachments
	 * @ticket 40351
	 *
	 * @param string $post_status New post status.
	 * @param int    $change      Expected change.
	 */
	public function test_term_count_changes_for_post_statuses_with_untermed_attachments( $post_status, $change ) {
		$term_count = get_term( self::$attachment_term )->count;
		// Do not use shared fixture for this test as it relies on a new post.
		$post_id = $this->factory()->post->create( array( 'post_status' => $post_status ) );
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $post_status New post status.
	 *     @type int    $change      Expected change.
	 * }
	 */
	function data_term_count_changes_for_post_statuses_with_untermed_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 1 ),
			// 1. Auto draft
			array( 'auto-draft', 0 ),
			// 2. Draft
			array( 'draft', 0 ),
			// 3. Private post
			array( 'private', 0 ),
		);
	}

	/**
	 * Term counts increments correctly when post status becomes published.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @covers ::wp_publish_post
	 * @dataProvider data_term_counts_incremented_on_publish_with_untermed_attachments
	 * @ticket 40351
	 * @ticket 51292
	 *
	 * @param string $original_post_status Post status prior to change to publish.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_counts_incremented_on_publish_with_untermed_attachments( $original_post_status, $change ) {
		$post_id = self::$post_ids[ $original_post_status ];
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		$term_count    = get_term( self::$attachment_term )->count;

		wp_publish_post( $post_id );

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_changes_for_post_statuses_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status prior to change to publish.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	public function data_term_counts_incremented_on_publish_with_untermed_attachments() {
		return array(
			// 0. Published post
			array( 'publish', 0 ),
			// 1. Auto draft
			array( 'auto-draft', 1 ),
			// 2. Draft
			array( 'draft', 1 ),
			// 3. Private post
			array( 'private', 1 ),
		);
	}

	/**
	 * Test post status transition update term counts correctly.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @dataProvider data_term_count_transitions_update_term_counts_with_untermed_attachments
	 * @ticket 40351
	 *
	 * @param string $original_post_status Post status upon create.
	 * @param string $new_post_status      Post status after update.
	 * @param int    $change               Expected change upon publish.
	 */
	public function test_term_count_transitions_update_term_counts_with_untermed_attachments( $original_post_status, $new_post_status, $change ) {
		$post_id = self::$post_ids[ $original_post_status ];
		wp_add_object_terms( $post_id, self::$attachment_term, 'wp_test_tax_counts' );
		$attachment_id = self::factory()->attachment->create_object(
			array(
				'file'        => 'image.jpg',
				'post_parent' => $post_id,
				'post_status' => 'inherit',
			)
		);
		$term_count    = get_term( self::$attachment_term )->count;

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_post_status,
			)
		);

		$expected = $term_count + $change;
		$this->assertSame( $expected, get_term( self::$attachment_term )->count );
	}

	/**
	 * Data provider for test_term_count_transitions_update_term_counts_with_untermed_attachments.
	 *
	 * @return array[] {
	 *     @type string $original_post_status Post status upon create.
	 *     @type string $new_post_status      Post status after update.
	 *     @type int    $change               Expected change upon publish.
	 * }
	 */
	public function data_term_count_transitions_update_term_counts_with_untermed_attachments() {
		return array(
			// 0. Draft -> published post
			array( 'draft', 'publish', 1 ),
			// 1. Auto draft -> published post
			array( 'auto-draft', 'publish', 1 ),
			// 2. Private -> published post
			array( 'private', 'publish', 1 ),
			// 3. Published -> published post
			array( 'publish', 'publish', 0 ),

			// 4. Draft -> private post
			array( 'draft', 'private', 0 ),
			// 5. Auto draft -> private post
			array( 'auto-draft', 'private', 0 ),
			// 6. Private -> private post
			array( 'private', 'private', 0 ),
			// 7. Published -> private post
			array( 'publish', 'private', -1 ),

			// 8. Draft -> draft post
			array( 'draft', 'draft', 0 ),
			// 9. Auto draft -> draft post
			array( 'auto-draft', 'draft', 0 ),
			// 10. Private -> draft post
			array( 'private', 'draft', 0 ),
			// 11. Published -> draft post
			array( 'publish', 'draft', -1 ),
		);
	}

	/**
	 * User taxonomy term counts increments when added to an account.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @ticket 51292
	 */
	public function test_term_counts_user_adding_term() {
		$term_count = get_term( self::$user_term )->count;
		wp_add_object_terms( self::$user_id, self::$user_term, 'wp_test_user_tax_counts' );

		$expected = $term_count + 1;
		$this->assertSame( $expected, get_term( self::$user_term )->count );
	}

	/**
	 * User taxonomy term counts decrement when term deleted from user.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @ticket 51292
	 */
	public function test_term_counts_user_removing_term() {
		wp_add_object_terms( self::$user_id, self::$user_term, 'wp_test_user_tax_counts' );
		$term_count = get_term( self::$user_term )->count;

		wp_remove_object_terms( self::$user_id, self::$user_term, 'wp_test_user_tax_counts' );
		$expected = $term_count - 1;
		$this->assertSame( $expected, get_term( self::$user_term )->count );
	}

	/**
	 * Ensure DB queries for deferred counts are nullified for net zero gain.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @covers ::wp_defer_term_counting
	 * @ticket 51292
	 */
	public function test_counts_after_deferral_net_zero() {
		$post_one = self::$post_ids['publish'];
		$post_two = self::$post_ids['publish_two'];
		$terms    = self::$tag_ids;

		wp_set_object_terms( $post_one, $terms[0], 'post_tag', true );

		// Net gain 0;
		wp_defer_term_counting( true );
		wp_remove_object_terms( $post_one, $terms[0], 'post_tag' );
		wp_set_object_terms( $post_two, $terms[0], 'post_tag', true );
		$num_queries = get_num_queries();
		wp_defer_term_counting( false );
		// Ensure number of queries unchanged.
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * Ensure full recounts follow modify by X recounts to avoid miscounts.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @covers ::wp_update_term_count
	 * @covers ::wp_defer_term_counting
	 * @ticket 51292
	 */
	public function test_counts_after_deferral_full_before_partial() {
		$post_one   = self::$post_ids['publish'];
		$terms      = self::$tag_ids;
		$term_count = get_term( $terms[0] )->count;

		// Net gain 1;
		wp_defer_term_counting( true );
		wp_set_object_terms( $post_one, $terms[0], 'post_tag', true );
		wp_update_term_count( get_term( $terms[0] )->term_taxonomy_id, 'post_tag' );
		wp_defer_term_counting( false );

		// Ensure term count is correct.
		$expected = $term_count + 1;
		$this->assertSame( $expected, get_term( $terms[0] )->count );
	}

	/**
	 * Ensure DB queries for deferred counts are combined.
	 *
	 * @covers ::wp_modify_term_count_by
	 * @covers ::wp_defer_term_counting
	 * @ticket 51292
	 */
	public function test_counts_after_deferral_matching_changes() {
		$post_one = self::$post_ids['publish'];
		$post_two = self::$post_ids['publish_two'];
		$terms    = self::$tag_ids;

		wp_set_object_terms( $post_one, $terms[0], 'post_tag', true );

		// Net gain 0:
		wp_defer_term_counting( true );
		wp_remove_object_terms( $post_one, $terms[0], 'post_tag' );
		wp_set_object_terms( $post_two, $terms[0], 'post_tag', true );

		// Net gain 1:
		wp_set_object_terms( $post_one, $terms[1], 'post_tag', true );
		wp_set_object_terms( $post_two, $terms[2], 'post_tag', true );

		// Net gain 2:
		wp_set_object_terms( $post_one, array( $terms[3], $terms[4] ), 'post_tag', true );
		wp_set_object_terms( $post_two, array( $terms[3], $terms[4] ), 'post_tag', true );

		$num_queries = get_num_queries();
		wp_defer_term_counting( false );

		/*
		 * Each count is expected to produce two queries:
		 * 1) The count update
		 * 2) The SELECT in `clean_term_cache()`.
		 */
		$expected = $num_queries + ( 2 * 2 );
		// Ensure number of queries correct.
		$this->assertSame( $expected, get_num_queries() );
	}
}
