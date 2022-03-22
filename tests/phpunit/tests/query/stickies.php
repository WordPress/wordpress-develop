<?php

/**
 * Tests related to sticky functionality in WP_Query.
 *
 * @group query
 */
class Tests_Query_Stickies extends WP_UnitTestCase {
	public static $posts         = array();
	protected $sticky_query_vars = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		// Set post times to get a reliable order.
		$now = time();
		for ( $i = 0; $i <= 22; $i++ ) {
			$post_date         = gmdate( 'Y-m-d H:i:s', $now - ( 10 * $i ) );
			self::$posts[ $i ] = $factory->post->create(
				array(
					'post_date' => $post_date,
				)
			);
		}

		stick_post( self::$posts[2] );
		stick_post( self::$posts[14] );
		stick_post( self::$posts[8] );
	}

	public function test_stickies_should_be_ignored_when_is_home_is_false() {
		$q = new WP_Query(
			array(
				'year'           => gmdate( 'Y' ),
				'fields'         => 'ids',
				'posts_per_page' => 3,
			)
		);

		$expected = array(
			self::$posts[0],
			self::$posts[1],
			self::$posts[2],
		);

		$this->assertSame( $expected, $q->posts );
	}

	public function test_stickies_should_be_included_when_is_home_is_true() {
		$this->go_to( '/' );

		$q = $GLOBALS['wp_query'];

		$this->assertSame( self::$posts[2], $q->posts[0]->ID );
		$this->assertSame( self::$posts[8], $q->posts[1]->ID );
		$this->assertSame( self::$posts[14], $q->posts[2]->ID );
	}

	public function test_stickies_should_not_be_included_on_pages_other_than_1() {
		$this->go_to( '/?paged=2' );

		$q = $GLOBALS['wp_query'];

		$found = wp_list_pluck( $q->posts, 'ID' );
		$this->assertNotContains( self::$posts[2], $found );
	}

	public function test_stickies_should_not_be_included_when_ignore_sticky_posts_is_true() {
		add_action( 'parse_query', array( $this, 'set_ignore_sticky_posts' ) );
		$this->go_to( '/' );
		remove_action( 'parse_query', array( $this, 'set_ignore_sticky_posts' ) );

		$q = $GLOBALS['wp_query'];

		$expected = array(
			self::$posts[0],
			self::$posts[1],
			self::$posts[2],
			self::$posts[3],
			self::$posts[4],
			self::$posts[5],
			self::$posts[6],
			self::$posts[7],
			self::$posts[8],
			self::$posts[9],
		);

		$this->assertSame( $expected, wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function test_stickies_should_obey_post__not_in() {
		add_action( 'parse_query', array( $this, 'set_post__not_in' ) );
		$this->go_to( '/' );
		remove_action( 'parse_query', array( $this, 'set_post__not_in' ) );

		$q = $GLOBALS['wp_query'];

		$this->assertSame( self::$posts[2], $q->posts[0]->ID );
		$this->assertSame( self::$posts[14], $q->posts[1]->ID );
		$this->assertNotContains( self::$posts[8], wp_list_pluck( $q->posts, 'ID' ) );
	}

	public function set_ignore_sticky_posts( $q ) {
		$q->set( 'ignore_sticky_posts', true );
	}

	public function set_post__not_in( $q ) {
		$q->set( 'post__not_in', array( self::$posts[8] ) );
	}

	/**
	 * @ticket 36907
	 */
	public function test_stickies_nest_query() {
		add_filter( 'posts_pre_query', array( $this, 'get_query_from_filter' ), 10, 2 );
		$this->go_to( '/' );

		$q = $GLOBALS['wp_query'];
		$this->assertNotEmpty( $this->sticky_query_vars['posts_per_page'] );
		$this->assertSame( $q->query_vars['suppress_filters'], $this->sticky_query_vars['suppress_filters'] );
		$this->assertSame( $q->query_vars['update_post_meta_cache'], $this->sticky_query_vars['update_post_meta_cache'] );
		$this->assertSame( $q->query_vars['update_post_term_cache'], $this->sticky_query_vars['update_post_term_cache'] );
		$this->assertSame( $q->query_vars['lazy_load_term_meta'], $this->sticky_query_vars['lazy_load_term_meta'] );
		$this->assertSame( $q->query_vars['cache_results'], $this->sticky_query_vars['cache_results'] );
		$this->assertTrue( $this->sticky_query_vars['ignore_sticky_posts'] );
		$this->assertTrue( $this->sticky_query_vars['no_found_rows'] );
	}

	public function get_query_from_filter( $results, $query ) {
		if ( ! empty( $query->query_vars['post__in'] ) && $query->query_vars['ignore_sticky_posts'] ) {
			$this->sticky_query_vars = $query->query_vars;
		}

		return $results;
	}
}
