<?php
/**
 * Test deterministic ordering functionality in WP_Query.
 *
 * @package WordPress\UnitTests
 *
 * @group query
 * @group ordering
 * @ticket 44349
 */
class Tests_Query_DeterministicOrdering extends WP_UnitTestCase {

	/**
	 * Test that deterministic ordering adds ID as tie-breaker for fields that can have duplicates.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_adds_id_tie_breaker() {
		global $wpdb;

		// Create posts with same post_date to test deterministic ordering
		$post1 = self::factory()->post->create( array(
			'post_title' => 'Post A',
			'post_date' => '2023-01-01 10:00:00',
		) );
		$post2 = self::factory()->post->create( array(
			'post_title' => 'Post B', 
			'post_date' => '2023-01-01 10:00:00', // Same date as post1
		) );
		$post3 = self::factory()->post->create( array(
			'post_title' => 'Post C',
			'post_date' => '2023-01-01 10:00:00', // Same date as post1 and post2
		) );

		// Test ordering by post_date (should add ID tie-breaker)
		$query = new WP_Query( array(
			'orderby' => 'post_date',
			'order' => 'ASC',
			'posts_per_page' => 10,
		) );

		// Verify SQL contains ID as secondary sort
		$this->assertStringContainsString( 'ORDER BY', $query->request );
		$this->assertStringContainsString( 'post_date ASC', $query->request );
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ASC ASC', $query->request ); // No double ASC
	}

	/**
	 * Test that deterministic ordering works with post_title.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_post_title() {
		// Create posts with same title to test deterministic ordering
		$post1 = self::factory()->post->create( array(
			'post_title' => 'Same Title',
			'post_date' => '2023-01-01 10:00:00',
		) );
		$post2 = self::factory()->post->create( array(
			'post_title' => 'Same Title', // Same title as post1
			'post_date' => '2023-01-01 11:00:00',
		) );

		$query = new WP_Query( array(
			'orderby' => 'post_title',
			'order' => 'ASC',
			'posts_per_page' => 10,
		) );

		// Verify SQL contains ID as secondary sort
		$this->assertStringContainsString( 'post_title ASC', $query->request );
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ASC ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with DESC order.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_desc_order() {
		$query = new WP_Query( array(
			'orderby' => 'post_date',
			'order' => 'DESC',
			'posts_per_page' => 10,
		) );

		// Verify SQL contains ID as secondary sort with DESC
		$this->assertStringContainsString( 'post_date DESC', $query->request );
		$this->assertStringContainsString( 'ID DESC', $query->request );
		$this->assertStringNotContainsString( 'DESC DESC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with array orderby.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_array_orderby() {
		$query = new WP_Query( array(
			'orderby' => array(
				'post_date' => 'ASC',
				'post_title' => 'ASC',
			),
			'posts_per_page' => 10,
		) );

		// Verify SQL contains both fields with directions
		$this->assertStringContainsString( 'post_date ASC', $query->request );
		$this->assertStringContainsString( 'post_title ASC', $query->request );
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ASC ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering doesn't add ID when ID is already present.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_does_not_duplicate_id() {
		$query = new WP_Query( array(
			'orderby' => 'ID',
			'order' => 'ASC',
			'posts_per_page' => 10,
		) );

		// Should not add duplicate ID
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ID ASC, ID ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with fields that don't need it.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_non_deterministic_fields() {
		$query = new WP_Query( array(
			'orderby' => 'rand',
			'posts_per_page' => 10,
		) );

		// Should not add ID tie-breaker for rand
		$this->assertStringContainsString( 'RAND()', $query->request );
		$this->assertStringNotContainsString( 'ID ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with default ordering.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_default_ordering() {
		$query = new WP_Query( array(
			'posts_per_page' => 10,
		) );

		// Default ordering should include ID tie-breaker
		$this->assertStringContainsString( 'post_date DESC', $query->request );
		$this->assertStringContainsString( 'ID DESC', $query->request );
		$this->assertStringNotContainsString( 'DESC DESC', $query->request );
	}

	/**
	 * Test that deterministic ordering prevents duplicate records across pages.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_prevents_duplicates_across_pages() {
		// Create multiple posts with same post_date
		$posts = array();
		for ( $i = 1; $i <= 10; $i++ ) {
			$posts[] = self::factory()->post->create( array(
				'post_title' => "Post $i",
				'post_date' => '2023-01-01 10:00:00', // All same date
			) );
		}

		// Get first page
		$query1 = new WP_Query( array(
			'orderby' => 'post_date',
			'order' => 'ASC',
			'posts_per_page' => 5,
			'paged' => 1,
		) );

		// Get second page
		$query2 = new WP_Query( array(
			'orderby' => 'post_date',
			'order' => 'ASC',
			'posts_per_page' => 5,
			'paged' => 2,
		) );

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// No overlap between pages
		$this->assertEmpty( array_intersect( $page1_ids, $page2_ids ) );

		// Total posts should equal sum of both pages
		$this->assertEquals( 10, $query1->found_posts );
		$this->assertEquals( 5, count( $page1_ids ) );
		$this->assertEquals( 5, count( $page2_ids ) );
	}

	/**
	 * Test that deterministic ordering works with search queries.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_search() {
		// Create posts with searchable content
		$post1 = self::factory()->post->create( array(
			'post_title' => 'Test Post 1',
			'post_content' => 'This is a test post',
			'post_date' => '2023-01-01 10:00:00',
		) );
		$post2 = self::factory()->post->create( array(
			'post_title' => 'Test Post 2',
			'post_content' => 'This is another test post',
			'post_date' => '2023-01-01 10:00:00', // Same date
		) );

		$query = new WP_Query( array(
			's' => 'test',
			'orderby' => 'post_date',
			'order' => 'ASC',
			'posts_per_page' => 10,
		) );

		// Should still have deterministic ordering even with search
		$this->assertStringContainsString( 'post_date ASC', $query->request );
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ASC ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with meta queries.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_meta_query() {
		// Create posts with meta values
		$post1 = self::factory()->post->create();
		add_post_meta( $post1, 'test_meta', 'value1' );

		$post2 = self::factory()->post->create();
		add_post_meta( $post2, 'test_meta', 'value2' );

		$query = new WP_Query( array(
			'meta_key' => 'test_meta',
			'orderby' => 'post_date',
			'order' => 'ASC',
			'posts_per_page' => 10,
		) );

		// Should still have deterministic ordering with meta queries
		$this->assertStringContainsString( 'post_date ASC', $query->request );
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ASC ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with taxonomy queries.
	 *
	 * @ticket 44349
	 */
	public function test_deterministic_ordering_with_taxonomy_query() {
		// Create posts with categories
		$post1 = self::factory()->post->create();
		$post2 = self::factory()->post->create();

		$cat_id = self::factory()->category->create( array( 'name' => 'Test Category' ) );
		wp_set_post_categories( $post1, array( $cat_id ) );
		wp_set_post_categories( $post2, array( $cat_id ) );

		$query = new WP_Query( array(
			'category_name' => 'test-category',
			'orderby' => 'post_date',
			'order' => 'ASC',
			'posts_per_page' => 10,
		) );

		// Should still have deterministic ordering with taxonomy queries
		$this->assertStringContainsString( 'post_date ASC', $query->request );
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ASC ASC', $query->request );
	}
}
