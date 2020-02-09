<?php

/**
 * Tests to make sure looping through posts using the wp_loop() function works properly.
 *
 * @ticket 48193
 *
 * @group  query
 */
class Tests_WP_Loop extends WP_UnitTestCase {

	/**
	 * Create test posts in database.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create( [ 'post_title' => 'Post 1' ] );
		$factory->post->create( [ 'post_title' => 'Post 2' ] );
		$factory->post->create( [ 'post_title' => 'Post 3' ] );
	}

	/**
	 * Test iterating over the global WP_Query instance.
	 */
	public function test_global_query() {

		// Ensure global query returns results in order by title.
		add_filter( 'pre_get_posts', function ( WP_Query $query ) {
			if ( $query->is_main_query() ) {
				$query->set( 'orderby', 'title' );
				$query->set( 'order', 'ASC' );
			}
		} );

		// Required to setup global WP_Query instance
		$this->go_to( '/' );

		$i = 0;
		foreach ( wp_loop() as $post ) {
			$i ++;
			$this->assertTrue( is_a( $post, 'WP_Post' ) );
			$this->assertEquals( "Post {$i}", $post->post_title );
			$this->assertEquals( "Post {$i}", get_the_title() );
		}

	}

	/**
	 * Test iterating over a custom WP_Query instance.
	 */
	public function test_custom_query() {

		// Create query
		$query = new WP_Query( [
			'post_type' => 'post',
			'order_by'  => 'title',
			'order'     => 'ASC',
		] );

		$i = 0;
		foreach ( wp_loop( $query ) as $post ) {
			$i ++;
			$this->assertTrue( is_a( $post, 'WP_Post' ) );
			$this->assertEquals( "Post {$i}", $post->post_title );
			$this->assertEquals( "Post {$i}", get_the_title() );
		}
	}

	/**
	 * Test iterating over an array of WP_Post objects.
	 */
	public function test_array_of_posts() {

		$posts = [
			get_page_by_title( 'Post 1', OBJECT, 'post' ),
			get_page_by_title( 'Post 2', OBJECT, 'post' ),
			get_page_by_title( 'Post 3', OBJECT, 'post' ),
		];

		$i = 0;
		foreach ( wp_loop( $posts ) as $post ) {
			$i ++;
			$this->assertTrue( is_a( $post, 'WP_Post' ) );
			$this->assertEquals( "Post {$i}", $post->post_title );
			$this->assertEquals( "Post {$i}", get_the_title() );
		}
	}

	/**
	 * Test iterating over an array of post IDs.
	 */
	public function test_array_of_post_ids() {

		$post_ids = [
			get_page_by_title( 'Post 1', OBJECT, 'post' )->ID,
			get_page_by_title( 'Post 2', OBJECT, 'post' )->ID,
			get_page_by_title( 'Post 3', OBJECT, 'post' )->ID,
		];

		$i = 0;
		foreach ( wp_loop( $post_ids ) as $post ) {
			$i ++;
			$this->assertTrue( is_a( $post, 'WP_Post' ) );
			$this->assertEquals( "Post {$i}", $post->post_title );
			$this->assertEquals( "Post {$i}", get_the_title() );
		}
	}

	/**
	 * Test iterating over an iterator.
	 */
	public function test_looping_over_iterator() {

		$posts = [
			get_page_by_title( 'Post 1', OBJECT, 'post' ),
			get_page_by_title( 'Post 2', OBJECT, 'post' ),
			get_page_by_title( 'Post 3', OBJECT, 'post' ),
		];

		$iterator = new ArrayIterator( $posts );

		$i = 0;
		foreach ( wp_loop( $iterator ) as $post ) {
			$i ++;
			$this->assertTrue( is_a( $post, 'WP_Post' ) );
			$this->assertEquals( "Post {$i}", $post->post_title );
			$this->assertEquals( "Post {$i}", get_the_title() );
		}
	}

}
