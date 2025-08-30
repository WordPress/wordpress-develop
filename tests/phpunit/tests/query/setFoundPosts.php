<?php

/**
 * @group query
 * @covers WP_Query::set_found_posts
 */
class Test_Query_setFoundPosts extends WP_UnitTestCase {
	/**
	 * Post IDs.
	 *
	 * @var int[]
	 */
	public static $posts;
	/**
	 * Post IDs.
	 *
	 * @var WP_Query
	 */
	public $q;


	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Make some post objects.
		self::$posts = $factory->post->create_many( 50 );
	}

	public function set_up() {
		parent::set_up();
		unset( $this->q );
		$this->q = new WP_Query();
	}

	/**
	 * testa the set_found_posts() function
	 *
	 * @ticket 18694
	 *
	 * @param array $q Query variables
	 * @param string $limits
	 * @param int $expected
	 * @param int $pages
	 *
	 * @dataProvider data_set_found_posts
	 *
	 */
	public function test_set_found_posts( $q, $limits, $expected, $pages ) {
		$this->q = new WP_Query( $q );
		$this->q->set_found_posts( $q, $limits );

		$this->assertSame( $expected, $this->q->found_posts );
		$this->assertSame( $pages, $this->q->max_num_pages );
	}

	public function data_set_found_posts() {
		return array(
			'10' => array(
				'q'        => array(
					'post_status'            => 'posts', // For the future post.
					'orderby'                => 'ID',  // Same order they were created.
					'order'                  => 'ASC',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'posts_per_page'         => 10,
				),
				'limits'   => 'not empty',
				'expected' => 10,
				'pages'    => 1,
			),

		);
	}
}
