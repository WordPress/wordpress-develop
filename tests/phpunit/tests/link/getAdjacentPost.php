<?php
/**
 * @group link
 * @covers ::get_adjacent_post
 */
class Tests_Link_GetAdjacentPost extends WP_UnitTestCase {
	protected $exclude_term;

	/**
	 * @ticket 17807
	 */
	public function test_get_adjacent_post() {
		// Need some sample posts to test adjacency.
		$post_one = self::factory()->post->create_and_get(
			array(
				'post_title' => 'First',
				'post_date'  => '2012-01-01 12:00:00',
			)
		);

		$post_two = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Second',
				'post_date'  => '2012-02-01 12:00:00',
			)
		);

		$post_three = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Third',
				'post_date'  => '2012-03-01 12:00:00',
			)
		);

		$post_four = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Fourth',
				'post_date'  => '2012-04-01 12:00:00',
			)
		);

		// Assign some terms.
		wp_set_object_terms( $post_one->ID, 'WordPress', 'category', false );
		wp_set_object_terms( $post_three->ID, 'WordPress', 'category', false );

		wp_set_object_terms( $post_two->ID, 'plugins', 'post_tag', false );
		wp_set_object_terms( $post_four->ID, 'plugins', 'post_tag', false );

		// Test normal post adjacency.
		$this->go_to( get_permalink( $post_two->ID ) );

		$this->assertEquals( $post_one, get_adjacent_post( false, '', true ) );
		$this->assertEquals( $post_three, get_adjacent_post( false, '', false ) );

		$this->assertNotEquals( $post_two, get_adjacent_post( false, '', true ) );
		$this->assertNotEquals( $post_two, get_adjacent_post( false, '', false ) );

		// Test category adjacency.
		$this->go_to( get_permalink( $post_one->ID ) );

		$this->assertSame( '', get_adjacent_post( true, '', true, 'category' ) );
		$this->assertEquals( $post_three, get_adjacent_post( true, '', false, 'category' ) );

		// Test tag adjacency.
		$this->go_to( get_permalink( $post_two->ID ) );

		$this->assertSame( '', get_adjacent_post( true, '', true, 'post_tag' ) );
		$this->assertEquals( $post_four, get_adjacent_post( true, '', false, 'post_tag' ) );

		// Test normal boundary post.
		$this->go_to( get_permalink( $post_two->ID ) );

		$this->assertEquals( array( $post_one ), get_boundary_post( false, '', true ) );
		$this->assertEquals( array( $post_four ), get_boundary_post( false, '', false ) );

		// Test category boundary post.
		$this->go_to( get_permalink( $post_one->ID ) );

		$this->assertEquals( array( $post_one ), get_boundary_post( true, '', true, 'category' ) );
		$this->assertEquals( array( $post_three ), get_boundary_post( true, '', false, 'category' ) );

		// Test tag boundary post.
		$this->go_to( get_permalink( $post_two->ID ) );

		$this->assertEquals( array( $post_two ), get_boundary_post( true, '', true, 'post_tag' ) );
		$this->assertEquals( array( $post_four ), get_boundary_post( true, '', false, 'post_tag' ) );
	}

	/**
	 * @ticket 22112
	 */
	public function test_get_adjacent_post_exclude_self_term() {
		// Bump term_taxonomy to mimic shared term offsets.
		global $wpdb;
		$wpdb->insert(
			$wpdb->term_taxonomy,
			array(
				'taxonomy'    => 'foo',
				'term_id'     => 12345,
				'description' => '',
			)
		);

		$include = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'name'     => 'Include',
			)
		);
		$exclude = self::factory()->category->create();

		$one = self::factory()->post->create_and_get(
			array(
				'post_date'     => '2012-01-01 12:00:00',
				'post_category' => array( $include, $exclude ),
			)
		);

		$two = self::factory()->post->create_and_get(
			array(
				'post_date'     => '2012-01-02 12:00:00',
				'post_category' => array(),
			)
		);

		$three = self::factory()->post->create_and_get(
			array(
				'post_date'     => '2012-01-03 12:00:00',
				'post_category' => array( $include, $exclude ),
			)
		);

		$four = self::factory()->post->create_and_get(
			array(
				'post_date'     => '2012-01-04 12:00:00',
				'post_category' => array( $include ),
			)
		);

		$five = self::factory()->post->create_and_get(
			array(
				'post_date'     => '2012-01-05 12:00:00',
				'post_category' => array( $include, $exclude ),
			)
		);

		// First post.
		$this->go_to( get_permalink( $one ) );
		$this->assertEquals( $two, get_adjacent_post( false, array(), false ) );
		$this->assertEquals( $three, get_adjacent_post( true, array(), false ) );
		$this->assertEquals( $two, get_adjacent_post( false, array( $exclude ), false ) );
		$this->assertEquals( $four, get_adjacent_post( true, array( $exclude ), false ) );
		$this->assertEmpty( get_adjacent_post( false, array(), true ) );

		// Fourth post.
		$this->go_to( get_permalink( $four ) );
		$this->assertEquals( $five, get_adjacent_post( false, array(), false ) );
		$this->assertEquals( $five, get_adjacent_post( true, array(), false ) );
		$this->assertEmpty( get_adjacent_post( false, array( $exclude ), false ) );
		$this->assertEmpty( get_adjacent_post( true, array( $exclude ), false ) );

		$this->assertEquals( $three, get_adjacent_post( false, array(), true ) );
		$this->assertEquals( $three, get_adjacent_post( true, array(), true ) );
		$this->assertEquals( $two, get_adjacent_post( false, array( $exclude ), true ) );
		$this->assertEmpty( get_adjacent_post( true, array( $exclude ), true ) );

		// Last post.
		$this->go_to( get_permalink( $five ) );
		$this->assertEquals( $four, get_adjacent_post( false, array(), true ) );
		$this->assertEquals( $four, get_adjacent_post( true, array(), true ) );
		$this->assertEquals( $four, get_adjacent_post( false, array( $exclude ), true ) );
		$this->assertEquals( $four, get_adjacent_post( true, array( $exclude ), true ) );
		$this->assertEmpty( get_adjacent_post( false, array(), false ) );
	}

	/**
	 * @ticket 32833
	 */
	public function test_get_adjacent_post_excluded_terms() {
		register_taxonomy( 'wptests_tax', 'post' );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p1 = self::factory()->post->create( array( 'post_date' => '2015-08-27 12:00:00' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2015-08-26 12:00:00' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2015-08-25 12:00:00' ) );

		wp_set_post_terms( $p2, array( $t ), 'wptests_tax' );

		// Fake current page.
		$_post           = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = get_post( $p1 );

		$found = get_adjacent_post( false, array( $t ), true, 'wptests_tax' );

		if ( ! is_null( $_post ) ) {
			$GLOBALS['post'] = $_post;
		} else {
			unset( $GLOBALS['post'] );
		}

		// Should skip $p2, which belongs to $t.
		$this->assertSame( $p3, $found->ID );
	}

	/**
	 * @ticket 32833
	 */
	public function test_get_adjacent_post_excluded_terms_should_not_require_posts_to_have_terms_in_any_taxonomy() {
		register_taxonomy( 'wptests_tax', 'post' );

		$t = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p1 = self::factory()->post->create( array( 'post_date' => '2015-08-27 12:00:00' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2015-08-26 12:00:00' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2015-08-25 12:00:00' ) );

		wp_set_post_terms( $p2, array( $t ), 'wptests_tax' );

		// Make sure that $p3 doesn't have the 'Uncategorized' category.
		wp_delete_object_term_relationships( $p3, 'category' );

		// Fake current page.
		$_post           = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = get_post( $p1 );

		$found = get_adjacent_post( false, array( $t ), true, 'wptests_tax' );

		if ( ! is_null( $_post ) ) {
			$GLOBALS['post'] = $_post;
		} else {
			unset( $GLOBALS['post'] );
		}

		// Should skip $p2, which belongs to $t.
		$this->assertSame( $p3, $found->ID );
	}

	/**
	 * @ticket 35211
	 */
	public function test_get_adjacent_post_excluded_terms_filter() {
		register_taxonomy( 'wptests_tax', 'post' );

		$terms = self::factory()->term->create_many(
			2,
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p1 = self::factory()->post->create( array( 'post_date' => '2015-08-27 12:00:00' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2015-08-26 12:00:00' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2015-08-25 12:00:00' ) );

		wp_set_post_terms( $p1, array( $terms[0], $terms[1] ), 'wptests_tax' );
		wp_set_post_terms( $p2, array( $terms[1] ), 'wptests_tax' );
		wp_set_post_terms( $p3, array( $terms[0] ), 'wptests_tax' );

		$this->go_to( get_permalink( $p1 ) );

		$this->exclude_term = $terms[1];
		add_filter( 'get_previous_post_excluded_terms', array( $this, 'filter_excluded_terms' ) );

		$found = get_adjacent_post( true, array(), true, 'wptests_tax' );

		remove_filter( 'get_previous_post_excluded_terms', array( $this, 'filter_excluded_terms' ) );
		unset( $this->exclude_term );

		$this->assertSame( $p3, $found->ID );
	}

	/**
	 * @ticket 43521
	 */
	public function test_get_adjacent_post_excluded_terms_filter_should_apply_to_empty_excluded_terms_parameter() {
		register_taxonomy( 'wptests_tax', 'post' );

		$terms = self::factory()->term->create_many(
			2,
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p1 = self::factory()->post->create( array( 'post_date' => '2015-08-27 12:00:00' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2015-08-26 12:00:00' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2015-08-25 12:00:00' ) );

		wp_set_post_terms( $p1, array( $terms[0], $terms[1] ), 'wptests_tax' );
		wp_set_post_terms( $p2, array( $terms[1] ), 'wptests_tax' );
		wp_set_post_terms( $p3, array( $terms[0] ), 'wptests_tax' );

		$this->go_to( get_permalink( $p1 ) );

		$this->exclude_term = $terms[1];
		add_filter( 'get_previous_post_excluded_terms', array( $this, 'filter_excluded_terms' ) );

		$found = get_adjacent_post( false, array(), true, 'wptests_tax' );

		remove_filter( 'get_previous_post_excluded_terms', array( $this, 'filter_excluded_terms' ) );
		unset( $this->exclude_term );

		$this->assertSame( $p3, $found->ID );
	}

	/**
	 * @ticket 43521
	 */
	public function test_excluded_terms_filter_empty() {
		register_taxonomy( 'wptests_tax', 'post' );

		$terms = self::factory()->term->create_many(
			2,
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$p1 = self::factory()->post->create( array( 'post_date' => '2015-08-27 12:00:00' ) );
		$p2 = self::factory()->post->create( array( 'post_date' => '2015-08-26 12:00:00' ) );
		$p3 = self::factory()->post->create( array( 'post_date' => '2015-08-25 12:00:00' ) );

		wp_set_post_terms( $p1, array( $terms[0], $terms[1] ), 'wptests_tax' );
		wp_set_post_terms( $p2, array( $terms[1] ), 'wptests_tax' );
		wp_set_post_terms( $p3, array( $terms[0] ), 'wptests_tax' );

		$this->go_to( get_permalink( $p1 ) );

		$this->exclude_term = $terms[1];
		add_filter( 'get_previous_post_excluded_terms', array( $this, 'filter_excluded_terms' ) );

		$found = get_adjacent_post( false, array(), true, 'wptests_tax' );

		remove_filter( 'get_previous_post_excluded_terms', array( $this, 'filter_excluded_terms' ) );
		unset( $this->exclude_term );

		$this->assertSame( $p3, $found->ID );
	}

	public function filter_excluded_terms( $excluded_terms ) {
		$excluded_terms[] = $this->exclude_term;
		return $excluded_terms;
	}

	/**
	 * @ticket 63920
	 */
	public function test_get_adjacent_post_returns_empty_string_when_wp_get_object_terms_returns_wp_error() {
		register_taxonomy( 'wptests_error_tax', 'post' );

		$term1_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_error_tax',
			)
		);

		$post1_id = self::factory()->post->create(
			array(
				'post_title' => 'First',
				'post_date'  => '2025-09-01 12:00:00',
			)
		);

		$post2_id = self::factory()->post->create(
			array(
				'post_title' => 'Second',
				'post_date'  => '2025-09-02 12:00:00',
			)
		);

		wp_set_post_terms( $post1_id, array( $term1_id ), 'wptests_error_tax' );
		wp_set_post_terms( $post2_id, array( $term1_id ), 'wptests_error_tax' );

		$this->go_to( get_permalink( $post2_id ) );

		add_filter(
			'wp_get_object_terms',
			static function () {
				return new WP_Error( 'test_error', 'Test error from wp_get_object_terms' );
			}
		);
		$result = get_adjacent_post( true, '', true, 'wptests_error_tax' );
		$this->assertSame( '', $result );
	}

	/**
	 * @ticket 63920
	 */
	public function test_get_adjacent_post_empty_term_array_after_exclusions() {
		register_taxonomy( 'wptests_tax', 'post' );

		$term1_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$post1_id = self::factory()->post->create(
			array(
				'post_title' => 'First',
				'post_date'  => '2025-01-01 12:00:00',
			)
		);

		$post2_id = self::factory()->post->create(
			array(
				'post_title' => 'Second',
				'post_date'  => '2025-02-01 12:00:00',
			)
		);

		wp_set_post_terms( $post1_id, array( $term1_id ), 'wptests_tax' );
		wp_set_post_terms( $post2_id, array( $term1_id ), 'wptests_tax' );

		$this->go_to( get_permalink( $post2_id ) );
		$result = get_adjacent_post( true, array( $term1_id ), true, 'wptests_tax' );
		$this->assertSame( '', $result );
	}

	/**
	 * @ticket 63920
	 */
	public function test_get_adjacent_post_term_array_processing_order() {
		register_taxonomy( 'wptests_tax', 'post' );

		$term1_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);
		$term2_id = self::factory()->term->create(
			array(
				'taxonomy' => 'wptests_tax',
			)
		);

		$post1_id = self::factory()->post->create(
			array(
				'post_title' => 'First',
				'post_date'  => '2025-01-01 12:00:00',
			)
		);

		$post2_id = self::factory()->post->create(
			array(
				'post_title' => 'Second',
				'post_date'  => '2025-02-01 12:00:00',
			)
		);

		$post3_id = self::factory()->post->create(
			array(
				'post_title' => 'Third',
				'post_date'  => '2025-03-01 12:00:00',
			)
		);

		// All posts have term1. post_two has term1 and term2.
		wp_set_post_terms( $post1_id, array( $term1_id ), 'wptests_tax' );
		wp_set_post_terms( $post2_id, array( $term1_id, $term2_id ), 'wptests_tax' );
		wp_set_post_terms( $post3_id, array( $term1_id ), 'wptests_tax' );

		// Set the current post to post_two.
		$this->go_to( get_permalink( $post2_id ) );

		// When we exclude term2, we should still get adjacent posts that share term1.
		$result = get_adjacent_post( true, array( $term2_id ), true, 'wptests_tax' );

		// Should find post_one (previous post that shares term1).
		$this->assertInstanceOf( WP_Post::class, $result );
		$this->assertEquals( $post1_id, $result->ID );

		// Test next post.
		$result = get_adjacent_post( true, array( $term2_id ), false, 'wptests_tax' );

		// Should find post_three (next post that shares term1).
		$this->assertInstanceOf( WP_Post::class, $result );
		$this->assertEquals( $post3_id, $result->ID );
	}

	/**
	 * @ticket 63920
	 */
	public function test_get_adjacent_post_invalid_taxonomy() {
		self::factory()->post->create(
			array(
				'post_title' => 'First',
				'post_date'  => '2025-01-01 12:00:00',
			)
		);

		$post2_id = self::factory()->post->create(
			array(
				'post_title' => 'Second',
				'post_date'  => '2025-02-01 12:00:00',
			)
		);

		$this->go_to( get_permalink( $post2_id ) );
		$result = get_adjacent_post( true, '', true, 'invalid_taxonomy' );
		$this->assertNull( $result );
	}

	/**
	 * @ticket 41131
	 */
	public function test_get_adjacent_post_cache() {
		// Need some sample posts to test adjacency.
		$post_one = self::factory()->post->create_and_get(
			array(
				'post_title' => 'First',
				'post_date'  => '2012-01-01 12:00:00',
			)
		);

		$post_two = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Second',
				'post_date'  => '2012-02-01 12:00:00',
			)
		);

		$post_three = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Third',
				'post_date'  => '2012-03-01 12:00:00',
			)
		);

		$post_four = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Fourth',
				'post_date'  => '2012-04-01 12:00:00',
			)
		);

		// Assign some terms.
		wp_set_object_terms( $post_one->ID, 'WordPress', 'category', false );
		wp_set_object_terms( $post_three->ID, 'WordPress', 'category', false );

		wp_set_object_terms( $post_two->ID, 'plugins', 'post_tag', false );
		wp_set_object_terms( $post_four->ID, 'plugins', 'post_tag', false );

		// Test normal post adjacency.
		$this->go_to( get_permalink( $post_two->ID ) );

		// Test getting the right result.
		$first_run = get_adjacent_post( false, '', true );
		$this->assertEquals( $post_one, $first_run, 'Did not get first post when on second post' );
		$this->assertNotEquals( $post_two, $first_run, 'Got second post when on second post' );

		// Query count to test caching.
		$num_queries = get_num_queries();
		$second_run  = get_adjacent_post( false, '', true );
		$this->assertNotEquals( $post_two, $second_run, 'Got second post when on second post on second run' );
		$this->assertEquals( $post_one, $second_run, 'Did not get first post when on second post on second run' );
		$this->assertSame( $num_queries, get_num_queries() );

		// Test creating new post busts cache.
		$post_five   = self::factory()->post->create_and_get(
			array(
				'post_title' => 'Five',
				'post_date'  => '2012-04-01 12:00:00',
			)
		);
		$num_queries = get_num_queries();

		$this->assertEquals( $post_one, get_adjacent_post( false, '', true ), 'Did not get first post after new post is added' );
		$this->assertSame( get_num_queries() - $num_queries, 1, 'Number of queries run was not one after new post is added' );

		$this->assertEquals( $post_four, get_adjacent_post( true, '', false ), 'Did not get forth post after new post is added' );
		$num_queries = get_num_queries();
		$this->assertEquals( $post_four, get_adjacent_post( true, '', false ), 'Did not get forth post after new post is added' );
		$this->assertSame( $num_queries, get_num_queries() );
		wp_set_object_terms( $post_four->ID, 'themes', 'post_tag', false );

		$num_queries = get_num_queries();
		$this->assertEquals( $post_four, get_adjacent_post( true, '', false ), 'Result of function call is wrong after after adding new term' );
		$this->assertSame( get_num_queries() - $num_queries, 2, 'Number of queries run was not two after adding new term' );
	}

	/**
	 * Test get_adjacent_post with posts having identical post_date.
	 *
	 * @ticket 8107
	 */
	public function test_get_adjacent_post_with_identical_dates() {
		$identical_date = '2024-01-01 12:00:00';

		// Create posts with identical dates but different IDs.
		$post_ids = array();
		for ( $i = 1; $i <= 5; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		// Test navigation from the middle post (ID: 3rd post).
		$current_post_id = $post_ids[2]; // 3rd post
		$this->go_to( get_permalink( $current_post_id ) );

		// Previous post should be the 2nd post (lower ID, same date).
		$previous = get_adjacent_post( false, '', true );
		$this->assertInstanceOf( 'WP_Post', $previous );
		$this->assertEquals( $post_ids[1], $previous->ID );

		// Next post should be the 4th post (higher ID, same date).
		$next = get_adjacent_post( false, '', false );
		$this->assertInstanceOf( 'WP_Post', $next );
		$this->assertEquals( $post_ids[3], $next->ID );
	}

	/**
	 * Test get_adjacent_post with mixed dates and identical dates.
	 *
	 * @ticket 8107
	 */
	public function test_get_adjacent_post_mixed_dates_with_identical_groups() {
		// Create posts with different dates.
		$post_early = self::factory()->post->create(
			array(
				'post_title' => 'Early Post',
				'post_date'  => '2024-01-01 10:00:00',
			)
		);

		// Create multiple posts with identical date.
		$identical_date = '2024-01-01 12:00:00';
		$post_ids       = array();
		for ( $i = 1; $i <= 3; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Identical Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		$post_late = self::factory()->post->create(
			array(
				'post_title' => 'Late Post',
				'post_date'  => '2024-01-01 14:00:00',
			)
		);

		// Test from first identical post.
		$this->go_to( get_permalink( $post_ids[0] ) );

		// Previous should be the early post (different date).
		$previous = get_adjacent_post( false, '', true );
		$this->assertInstanceOf( 'WP_Post', $previous );
		$this->assertEquals( $post_early, $previous->ID );

		// Next should be the second identical post (same date, higher ID).
		$next = get_adjacent_post( false, '', false );
		$this->assertInstanceOf( 'WP_Post', $next );
		$this->assertEquals( $post_ids[1], $next->ID );

		// Test from middle identical post.
		$this->go_to( get_permalink( $post_ids[1] ) );

		// Previous should be the first identical post (same date, lower ID).
		$previous = get_adjacent_post( false, '', true );
		$this->assertInstanceOf( 'WP_Post', $previous );
		$this->assertEquals( $post_ids[0], $previous->ID );

		// Next should be the third identical post (same date, higher ID).
		$next = get_adjacent_post( false, '', false );
		$this->assertInstanceOf( 'WP_Post', $next );
		$this->assertEquals( $post_ids[2], $next->ID );

		// Test from last identical post.
		$this->go_to( get_permalink( $post_ids[2] ) );

		// Previous should be the second identical post (same date, lower ID).
		$previous = get_adjacent_post( false, '', true );
		$this->assertInstanceOf( 'WP_Post', $previous );
		$this->assertEquals( $post_ids[1], $previous->ID );

		// Next should be the late post (different date).
		$next = get_adjacent_post( false, '', false );
		$this->assertInstanceOf( 'WP_Post', $next );
		$this->assertEquals( $post_late, $next->ID );
	}

	/**
	 * Test get_adjacent_post navigation through all posts with identical dates.
	 *
	 * @ticket 8107
	 */
	public function test_get_adjacent_post_navigation_through_identical_dates() {
		$identical_date = '2024-01-01 12:00:00';

		// Create 4 posts with identical dates.
		$post_ids = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		// Test navigation sequence: 1 -> 2 -> 3 -> 4.
		$this->go_to( get_permalink( $post_ids[0] ) );

		// From post 1, next should be post 2.
		$next = get_adjacent_post( false, '', false );
		$this->assertEquals( $post_ids[1], $next->ID );

		// From post 2, previous should be post 1, next should be post 3.
		$this->go_to( get_permalink( $post_ids[1] ) );
		$previous = get_adjacent_post( false, '', true );
		$this->assertEquals( $post_ids[0], $previous->ID );
		$next = get_adjacent_post( false, '', false );
		$this->assertEquals( $post_ids[2], $next->ID );

		// From post 3, previous should be post 2, next should be post 4.
		$this->go_to( get_permalink( $post_ids[2] ) );
		$previous = get_adjacent_post( false, '', true );
		$this->assertEquals( $post_ids[1], $previous->ID );
		$next = get_adjacent_post( false, '', false );
		$this->assertEquals( $post_ids[3], $next->ID );

		// From post 4, previous should be post 3.
		$this->go_to( get_permalink( $post_ids[3] ) );
		$previous = get_adjacent_post( false, '', true );
		$this->assertEquals( $post_ids[2], $previous->ID );
	}

	/**
	 * Test get_adjacent_post with identical dates and category filtering.
	 *
	 * @ticket 8107
	 */
	public function test_get_adjacent_post_identical_dates_with_category() {
		$identical_date = '2024-01-01 12:00:00';
		$category_id    = self::factory()->category->create( array( 'name' => 'Test Category' ) );

		// Create posts with identical dates, some in category.
		$post_ids = array();
		for ( $i = 1; $i <= 4; $i++ ) {
			$post_id = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);

			// Add every other post to the category.
			if ( 0 === $i % 2 ) {
				wp_set_post_categories( $post_id, array( $category_id ) );
			}

			$post_ids[] = $post_id;
		}

		// Test from post 2 (in category).
		$this->go_to( get_permalink( $post_ids[1] ) );

		// With category filtering, should only see posts in same category.
		$previous = get_adjacent_post( true, '', true, 'category' );
		$this->assertSame( '', $previous ); // No previous post in category

		$next = get_adjacent_post( true, '', false, 'category' );
		$this->assertInstanceOf( 'WP_Post', $next );
		$this->assertEquals( $post_ids[3], $next->ID ); // Post 4 (in category)
	}
}
