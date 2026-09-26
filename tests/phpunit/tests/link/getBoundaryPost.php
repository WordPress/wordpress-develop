<?php

/**
 * @group link
 * @covers ::get_boundary_post
 */
class Tests_Link_GetBoundaryPost extends WP_UnitTestCase {

	/**
	 * An array of post IDs.
	 *
	 * @var int[]
	 */
	protected static $post_ids;

	/**
	 * Set up the test fixture.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Create posts with specific dates to establish clear boundaries.
		self::$post_ids = array(
			'first'  => $factory->post->create( array( 'post_date' => '2020-01-01 12:00:00' ) ),
			'middle' => $factory->post->create( array( 'post_date' => '2021-01-01 12:00:00' ) ),
			'last'   => $factory->post->create( array( 'post_date' => '2022-01-01 12:00:00' ) ),
		);
	}

	/**
	 * @ticket 22957
	 */
	public function test_should_work_on_non_singular_pages() {
		// Navigate to the homepage, which is not a single post page.
		$this->go_to( home_url( '/' ) );
		$this->assertFalse( is_single(), 'This test should be running on a non-single page.' );

		// Test for the first post ($start = true, $in_same_term = false).
		$first_boundary_post = get_boundary_post( false, '', true );
		$this->assertIsArray( $first_boundary_post, 'get_boundary_post() should return an array for the first post.' );
		$this->assertNotEmpty( $first_boundary_post, 'get_boundary_post() should not return an empty value for the first post.' );
		$this->assertSame( self::$post_ids['first'], $first_boundary_post[0]->ID, 'The first boundary post ID is incorrect.' );

		// Test for the last post ($start = false, $in_same_term = false).
		$last_boundary_post = get_boundary_post( false, '', false );
		$this->assertIsArray( $last_boundary_post, 'get_boundary_post() should return an array for the last post.' );
		$this->assertNotEmpty( $last_boundary_post, 'get_boundary_post() should not return an empty value for the last post.' );
		$this->assertSame( self::$post_ids['last'], $last_boundary_post[0]->ID, 'The last boundary post ID is incorrect.' );
	}

	/**
	 * @ticket 22957
	 */
	public function test_should_still_work_on_single_pages() {
		// Navigate to a single post page.
		$this->go_to( get_permalink( self::$post_ids['middle'] ) );
		$this->assertTrue( is_single(), 'This test should be running on a single page.' );

		// Test for the first post ($start = true).
		$first_boundary_post = get_boundary_post( false, '', true );
		$this->assertIsArray( $first_boundary_post, 'get_boundary_post() should return an array on a single page.' );
		$this->assertNotEmpty( $first_boundary_post, 'get_boundary_post() should not return an empty value for the first post on a single page.' );
		$this->assertSame( self::$post_ids['first'], $first_boundary_post[0]->ID, 'The first boundary post ID is incorrect on a single page.' );

		// Test for the last post ($start = false).
		$last_boundary_post = get_boundary_post( false, '', false );
		$this->assertIsArray( $last_boundary_post, 'get_boundary_post() should return an array for the last post on a single page.' );
		$this->assertNotEmpty( $last_boundary_post, 'get_boundary_post() should not return an empty value for the last post on a single page.' );
		$this->assertSame( self::$post_ids['last'], $last_boundary_post[0]->ID, 'The last boundary post ID is incorrect on a single page.' );
	}

	/**
	 * @ticket 22957
	 */
	public function test_should_work_on_archive_pages() {
		// Create a category and assign posts to test archive functionality.
		$cat_id = self::factory()->category->create();
		wp_set_post_categories( self::$post_ids['first'], array( $cat_id ) );
		wp_set_post_categories( self::$post_ids['last'], array( $cat_id ) );

		// Navigate to a category archive page.
		$this->go_to( get_category_link( $cat_id ) );
		$this->assertTrue( is_category(), 'This test should be running on a category archive page.' );

		// Test boundary posts work on archive pages.
		$first_boundary_post = get_boundary_post( false, '', true );
		$this->assertIsArray( $first_boundary_post );
		$this->assertNotEmpty( $first_boundary_post );
		$this->assertSame( self::$post_ids['first'], $first_boundary_post[0]->ID );

		$last_boundary_post = get_boundary_post( false, '', false );
		$this->assertIsArray( $last_boundary_post );
		$this->assertNotEmpty( $last_boundary_post );
		$this->assertSame( self::$post_ids['last'], $last_boundary_post[0]->ID );
	}

	/**
	 * @ticket 22957
	 */
	public function test_should_respect_in_same_term_parameter() {
		// Create categories.
		$cat1 = self::factory()->category->create();
		$cat2 = self::factory()->category->create();

		// Assign different categories to posts.
		wp_set_post_categories( self::$post_ids['first'], array( $cat1 ) );
		wp_set_post_categories( self::$post_ids['middle'], array( $cat1 ) );
		wp_set_post_categories( self::$post_ids['last'], array( $cat2 ) );

		// Navigate to the middle post to provide current post context.
		$this->go_to( get_permalink( self::$post_ids['middle'] ) );
		$this->assertTrue( is_single(), 'This test should be running on a single page.' );

		// Test with $in_same_term = true (should find posts in same category as current post).
		$first_boundary_post = get_boundary_post( true, '', true );
		$this->assertIsArray( $first_boundary_post );
		$this->assertSame( self::$post_ids['first'], $first_boundary_post[0]->ID, 'Should find first post in same category.' );

		$last_boundary_post = get_boundary_post( true, '', false );
		$this->assertIsArray( $last_boundary_post );
		$this->assertSame( self::$post_ids['middle'], $last_boundary_post[0]->ID, 'Should find middle post as last in same category.' );

		// Test with $in_same_term = false (should work normally across all posts).
		$first_boundary_post_all = get_boundary_post( false, '', true );
		$this->assertIsArray( $first_boundary_post_all );
		$this->assertSame( self::$post_ids['first'], $first_boundary_post_all[0]->ID );

		$last_boundary_post_all = get_boundary_post( false, '', false );
		$this->assertIsArray( $last_boundary_post_all );
		$this->assertSame( self::$post_ids['last'], $last_boundary_post_all[0]->ID );
	}

	/**
	 * @ticket 22957
	 */
	public function test_should_return_null_for_invalid_taxonomy() {
		$this->go_to( home_url( '/' ) );

		$boundary_post = get_boundary_post( false, '', true, 'nonexistent_taxonomy' );
		$this->assertNull( $boundary_post, 'get_boundary_post() should return null for non-existent taxonomy.' );
	}
}
