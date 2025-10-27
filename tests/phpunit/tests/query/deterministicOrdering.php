<?php
/**
 * Test deterministic ordering functionality in WP_Query.
 *
 * @package WordPress\UnitTests
 *
 * @group query
 * @group ordering
 * @ticket xxxxx
 */
class Tests_Query_DeterministicOrdering extends WP_UnitTestCase {

	/**
	 * Test that deterministic ordering prevents duplicate records across pages.
	 *
	 * This is the core test for the bug fix. When multiple posts have the same
	 * value for a field (like post_date), pagination can show duplicate records
	 * without deterministic ordering.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_prevents_duplicates_across_pages() {
		// Create multiple posts with identical post_date to trigger the bug
		$identical_date = '2023-01-01 10:00:00';
		$post_ids       = array();

		for ( $i = 1; $i <= 20; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		// Get first page
		$query1 = new WP_Query(
			array(
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		// Get second page
		$query2 = new WP_Query(
			array(
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no overlap between pages (no duplicates)
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts' );

		// Verify total count is correct
		$this->assertEquals( 20, $query1->found_posts, 'Total posts should be 20' );
		$this->assertEquals( 10, count( $page1_ids ), 'First page should have 10 posts' );
		$this->assertEquals( 10, count( $page2_ids ), 'Second page should have 10 posts' );

		// Verify deterministic ordering: same query should return same results
		$query1_repeat    = new WP_Query(
			array(
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);
		$page1_repeat_ids = wp_list_pluck( $query1_repeat->posts, 'ID' );

		$this->assertEquals( $page1_ids, $page1_repeat_ids, 'Same query should return same results' );
	}

	/**
	 * Test that deterministic ordering works with post_title field.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_post_title() {
		$identical_title = 'Same Title';
		$post_ids        = array();

		for ( $i = 1; $i <= 15; $i++ ) {
			$post_day   = str_pad( (string) $i, 2, '0', STR_PAD_LEFT );
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => $identical_title,
					'post_date'  => "2023-01-{$post_day} 10:00:00",
				)
			);
		}

		// Get first page
		$query1 = new WP_Query(
			array(
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => 8,
				'paged'          => 1,
			)
		);

		// Get second page
		$query2 = new WP_Query(
			array(
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'posts_per_page' => 8,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts when ordering by title' );
	}

	/**
	 * Test that deterministic ordering works with DESC order.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_desc_order() {
		$identical_date = '2023-01-01 10:00:00';
		$post_ids       = array();

		for ( $i = 1; $i <= 12; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		// Get first page with DESC order
		$query1 = new WP_Query(
			array(
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'posts_per_page' => 6,
				'paged'          => 1,
			)
		);

		// Get second page with DESC order
		$query2 = new WP_Query(
			array(
				'orderby'        => 'post_date',
				'order'          => 'DESC',
				'posts_per_page' => 6,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts with DESC order' );
	}

	/**
	 * Test that deterministic ordering works with array orderby.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_array_orderby() {
		$identical_date = '2023-01-01 10:00:00';
		$post_ids       = array();

		for ( $i = 1; $i <= 16; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		// Test with array orderby
		$query1 = new WP_Query(
			array(
				'orderby'        => array(
					'post_date'  => 'ASC',
					'post_title' => 'ASC',
				),
				'posts_per_page' => 8,
				'paged'          => 1,
			)
		);

		$query2 = new WP_Query(
			array(
				'orderby'        => array(
					'post_date'  => 'ASC',
					'post_title' => 'ASC',
				),
				'posts_per_page' => 8,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts with array orderby' );
	}

	/**
	 * Test that deterministic ordering doesn't add ID when ID is already present.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_does_not_duplicate_id() {
		$identical_date = '2023-01-01 10:00:00';
		$post_ids       = array();

		for ( $i = 1; $i <= 10; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title' => "Post $i",
					'post_date'  => $identical_date,
				)
			);
		}

		$query = new WP_Query(
			array(
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => 10,
			)
		);

		// Should not add duplicate ID ordering
		$this->assertStringContainsString( 'ID ASC', $query->request );
		$this->assertStringNotContainsString( 'ID ASC, ID ASC', $query->request );
	}

	/**
	 * Test that deterministic ordering works with search queries.
	 *
	 * @ticket xxxxx
	 */
	public function test_deterministic_ordering_with_search() {
		$identical_date = '2023-01-01 10:00:00';
		$post_ids       = array();

		for ( $i = 1; $i <= 12; $i++ ) {
			$post_ids[] = self::factory()->post->create(
				array(
					'post_title'   => "Test Post $i",
					'post_content' => 'This is a test post',
					'post_date'    => $identical_date,
				)
			);
		}

		// Test with search
		$query1 = new WP_Query(
			array(
				's'              => 'test',
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 6,
				'paged'          => 1,
			)
		);

		$query2 = new WP_Query(
			array(
				's'              => 'test',
				'orderby'        => 'post_date',
				'order'          => 'ASC',
				'posts_per_page' => 6,
				'paged'          => 2,
			)
		);

		$page1_ids = wp_list_pluck( $query1->posts, 'ID' );
		$page2_ids = wp_list_pluck( $query2->posts, 'ID' );

		// Verify no duplicates across pages even with search
		$overlap = array_intersect( $page1_ids, $page2_ids );
		$this->assertEmpty( $overlap, 'Pages should not contain duplicate posts even with search' );
	}
}
