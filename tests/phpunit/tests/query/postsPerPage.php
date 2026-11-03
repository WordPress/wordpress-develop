<?php

/**
 * @group query
 * @covers WP_Query::get_posts
 */
class Tests_Query_PostsPerPage extends WP_UnitTestCase {

	/**
	 * @ticket 24142
	 * @dataProvider data_zero_posts_per_page
	 *
	 * @param int|string|bool $posts_per_page Page size.
	 * @param string          $fields         Fields to return.
	 */
	public function test_zero_posts_per_page_returns_no_posts_with_stickies( $posts_per_page, $fields ) {
		$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		stick_post( $post_id );

		// Check the initial query and a repeat query using cached results.
		for ( $i = 0; $i < 2; $i++ ) {
			$query = new WP_Query(
				array(
					'posts_per_page' => $posts_per_page,
					'fields'         => $fields,
				)
			);

			$this->assertTrue( $query->is_home() );
			$this->assertSame( array(), $query->posts, 'Zero-sized queries should not include sticky posts.' );
			$this->assertSame( 0, $query->post_count );
			$this->assertSame( 0, $query->found_posts );
			$this->assertSame( 0, $query->max_num_pages );
		}
	}

	/**
	 * @return array
	 */
	public static function data_zero_posts_per_page() {
		return array(
			'zero with post objects'        => array( 0, '' ),
			'false with post objects'       => array( false, '' ),
			'string zero with post objects' => array( '0', '' ),
			'zero with post IDs'            => array( 0, 'ids' ),
			'false with post IDs'           => array( false, 'ids' ),
			'string zero with post IDs'     => array( '0', 'ids' ),
		);
	}

	/**
	 * @ticket 24142
	 * @dataProvider data_zero_posts_per_archive_page
	 *
	 * @param array           $query_args             Archive or search query arguments.
	 * @param int|string|bool $posts_per_archive_page Page size override.
	 */
	public function test_zero_posts_per_archive_page_returns_no_posts( $query_args, $posts_per_archive_page ) {
		self::factory()->post->create( array( 'post_title' => 'Zero page size test' ) );

		$query_args['posts_per_page']         = 1;
		$query_args['posts_per_archive_page'] = $posts_per_archive_page;
		$query                                = new WP_Query( $query_args );

		$this->assertTrue( $query->is_archive() || $query->is_search() );
		$this->assertSame( array(), $query->posts );
		$this->assertSame( 0, $query->post_count );
		$this->assertSame( 0, $query->found_posts );
		$this->assertSame( 0, $query->max_num_pages );
	}

	/**
	 * @return array
	 */
	public static function data_zero_posts_per_archive_page() {
		return array(
			'archive with zero'        => array( array( 'year' => gmdate( 'Y' ) ), 0 ),
			'archive with false'       => array( array( 'year' => gmdate( 'Y' ) ), false ),
			'archive with string zero' => array( array( 'year' => gmdate( 'Y' ) ), '0' ),
			'search with zero'         => array( array( 's' => 'Zero page size test' ), 0 ),
			'search with false'        => array( array( 's' => 'Zero page size test' ), false ),
			'search with string zero'  => array( array( 's' => 'Zero page size test' ), '0' ),
		);
	}

	/**
	 * @ticket 24142
	 * @dataProvider data_invalid_posts_per_page
	 *
	 * @param mixed $posts_per_page Invalid page size.
	 */
	public function test_invalid_posts_per_page_uses_configured_default( $posts_per_page ) {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3 );

		$query = new WP_Query(
			array(
				'posts_per_page' => $posts_per_page,
				'fields'         => 'ids',
			)
		);

		$this->assertCount( 2, $query->posts );
		$this->assertSame( 2, $query->get( 'posts_per_page' ) );
	}

	/**
	 * @return array
	 */
	public static function data_invalid_posts_per_page() {
		return array(
			'non-numeric string' => array( 'foo' ),
			'null'               => array( null ),
			'empty string'       => array( '' ),
			'array'              => array( array() ),
		);
	}
}
