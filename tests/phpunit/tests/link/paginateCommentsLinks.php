<?php

/**
 * Tests for the paginate_comments_links() function.
 *
 * @group link
 * @group comment
 * @covers ::paginate_comments_links
 */
class Tests_Link_PaginateCommentsLinks extends WP_UnitTestCase {

	/**
	 * Post ID for testing.
	 *
	 * @var int
	 */
	protected static $post_id;

	/**
	 * Set up test fixtures before class.
	 *
	 * @param WP_UnitTest_Factory $factory Test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_id = $factory->post->create();
	}

	/**
	 * Clean up after class.
	 */
	public static function wpTearDownAfterClass() {
		wp_delete_post( self::$post_id, true );
	}

	/**
	 * Tests that paginate_comments_links() returns null when not on a singular request.
	 *
	 * @ticket 65819
	 */
	public function test_paginate_comments_links_returns_null_when_not_singular() {
		$this->go_to( home_url( '/' ) );

		$this->assertNull(
			paginate_comments_links(
				array(
					'total'   => 2,
					'current' => 1,
					'echo'    => false,
				)
			)
		);
	}

	/**
	 * Tests paginate_comments_links() returns markup with plain permalinks.
	 *
	 * @ticket 65819
	 */
	public function test_paginate_comments_links_plain_permalinks() {
		$this->go_to( get_permalink( self::$post_id ) );

		$output = paginate_comments_links(
			array(
				'total'   => 3,
				'current' => 2,
				'echo'    => false,
			)
		);

		$this->assertStringContainsString( 'cpage=1#comments', $output );
		$this->assertStringContainsString( 'cpage=3#comments', $output );
		$this->assertStringContainsString( 'class="page-numbers current"', $output );
	}

	/**
	 * Tests paginate_comments_links() returns markup with pretty permalinks.
	 *
	 * @ticket 65819
	 */
	public function test_paginate_comments_links_pretty_permalinks() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );
		$this->go_to( get_permalink( self::$post_id ) );

		$output = paginate_comments_links(
			array(
				'total'   => 3,
				'current' => 2,
				'echo'    => false,
			)
		);

		$this->assertStringContainsString( 'comment-page-1/#comments', $output );
		$this->assertStringContainsString( 'comment-page-3/#comments', $output );
		$this->assertStringContainsString( 'class="page-numbers current"', $output );
	}

	/**
	 * Tests that paginate_comments_links() echoes markup when 'echo' is true.
	 *
	 * @ticket 65819
	 */
	public function test_paginate_comments_links_echoes_by_default() {
		$this->go_to( get_permalink( self::$post_id ) );

		$args = array(
			'total'   => 2,
			'current' => 1,
		);

		$output = get_echo( 'paginate_comments_links', array( $args ) );

		$this->assertStringContainsString( 'cpage=2#comments', $output );
	}

	/**
	 * Tests that paginate_comments_links() returns an array when 'type' is 'array'.
	 *
	 * @ticket 65819
	 */
	public function test_paginate_comments_links_type_array() {
		$this->go_to( get_permalink( self::$post_id ) );

		$this->expectOutputString( '' );

		// Even with 'echo' => true, 'type' => 'array' should return array and not echo.
		$links = paginate_comments_links(
			array(
				'total'   => 3,
				'current' => 1,
				'type'    => 'array',
				'echo'    => true,
			)
		);

		$this->assertIsArray( $links );
		$this->assertCount( 4, $links ); // Page 1 (current), Page 2, Page 3, Next link.
		$this->assertStringContainsString( 'class="page-numbers current"', $links[0] );
		$this->assertStringContainsString( 'class="next page-numbers"', $links[3] );
	}

	/**
	 * Tests that paginate_comments_links() respects the 'cpage' query var when 'current' is omitted.
	 *
	 * @ticket 65819
	 */
	public function test_paginate_comments_links_respects_cpage_query_var() {
		$this->go_to( get_permalink( self::$post_id ) );
		set_query_var( 'cpage', 3 );

		$output = paginate_comments_links(
			array(
				'total' => 4,
				'echo'  => false,
			)
		);

		$this->assertStringContainsString( '<span aria-current="page" class="page-numbers current">3</span>', $output );
	}
}
