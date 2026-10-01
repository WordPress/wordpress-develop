<?php

/**
 * @group comment
 *
 * @covers ::wp_list_comments
 */
class Tests_Comment_WpListComments extends WP_UnitTestCase {

	/**
	 * Performs setup tasks for every test.
	 */
	public function set_up() {
		parent::set_up();
		switch_theme( 'default' );
	}

	/**
	 * @ticket 35175
	 */
	public function test_should_respect_page_param() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
				)
			);
		}

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );

		$this->go_to( get_permalink( $p ) );

		// comments_template() populates $wp_query->comments.
		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'page' => 2,
				'echo' => false,
			)
		);

		preg_match_all( '|id="comment\-([0-9]+)"|', $found, $matches );

		$this->assertEqualSets( array( $comments[2], $comments[3] ), $matches[1] );
	}

	/**
	 * @ticket 35175
	 */
	public function test_should_respect_per_page_param() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
				)
			);
		}

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );

		$this->go_to( get_permalink( $p ) );

		// comments_template() populates $wp_query->comments.
		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'per_page' => 3,
				'echo'     => false,
			)
		);

		preg_match_all( '|id="comment\-([0-9]+)"|', $found, $matches );

		$this->assertEqualSets( array( $comments[0], $comments[1], $comments[2] ), $matches[1] );
	}

	/**
	 * @ticket 35175
	 */
	public function test_should_respect_reverse_top_level_param() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
				)
			);
		}

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );

		$this->go_to( get_permalink( $p ) );

		// comments_template() populates $wp_query->comments.
		get_echo( 'comments_template' );

		$found1 = wp_list_comments(
			array(
				'reverse_top_level' => true,
				'echo'              => false,
			)
		);
		preg_match_all( '|id="comment\-([0-9]+)"|', $found1, $matches );
		$this->assertSame( array( $comments[0], $comments[1] ), array_map( 'intval', $matches[1] ) );

		$found2 = wp_list_comments(
			array(
				'reverse_top_level' => false,
				'echo'              => false,
			)
		);
		preg_match_all( '|id="comment\-([0-9]+)"|', $found2, $matches );
		$this->assertSame( array( $comments[1], $comments[0] ), array_map( 'intval', $matches[1] ) );
	}

	/**
	 * @ticket 35356
	 * @ticket 35175
	 */
	public function test_comments_param_should_be_respected_when_custom_pagination_params_are_passed() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
				)
			);
		}

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );

		$_comments = array( get_comment( $comments[1] ), get_comment( $comments[3] ) );

		// Populate `$wp_query->comments` in order to show that it doesn't override `$_comments`.
		$this->go_to( get_permalink( $p ) );
		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'echo'     => false,
				'per_page' => 1,
				'page'     => 2,
			),
			$_comments
		);

		preg_match_all( '|id="comment\-([0-9]+)"|', $found, $matches );
		$this->assertSame( array( $comments[3] ), array_map( 'intval', $matches[1] ) );
	}

	/**
	 * @ticket 37048
	 */
	public function test_custom_pagination_should_not_result_in_unapproved_comments_being_shown() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
				)
			);
		}

		// Only 2 and 5 are approved.
		wp_set_comment_status( $comments[0], '0' );
		wp_set_comment_status( $comments[1], '0' );
		wp_set_comment_status( $comments[3], '0' );
		wp_set_comment_status( $comments[4], '0' );

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );

		$this->go_to( get_permalink( $p ) );

		// comments_template() populates $wp_query->comments.
		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'echo'     => false,
				'per_page' => 1,
				'page'     => 2,
			)
		);

		preg_match_all( '|id="comment\-([0-9]+)"|', $found, $matches );
		$this->assertSame( array( $comments[2] ), array_map( 'intval', $matches[1] ) );
	}

	/**
	 * @ticket 37048
	 */
	public function test_custom_pagination_should_allow_ones_own_unapproved_comments() {
		$p = self::factory()->post->create();
		$u = self::factory()->user->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 5; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
					'user_id'          => $u,
				)
			);
		}

		// Only 2 and 5 are approved.
		wp_set_comment_status( $comments[0], '0' );
		wp_set_comment_status( $comments[1], '0' );
		wp_set_comment_status( $comments[3], '0' );
		wp_set_comment_status( $comments[4], '0' );

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );

		wp_set_current_user( $u );

		$this->go_to( get_permalink( $p ) );

		// comments_template() populates $wp_query->comments.
		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'echo'     => false,
				'per_page' => 1,
				'page'     => 2,
			)
		);

		preg_match_all( '|id="comment\-([0-9]+)"|', $found, $matches );
		$this->assertSame( array( $comments[4] ), array_map( 'intval', $matches[1] ) );
	}
	/**
	 * Creates a post with a set of comments.
	 *
	 * Comments are created newest first, so `$comments[0]` is the newest and
	 * `$comments[ $count - 1 ]` is the oldest. Comment lists paginate oldest
	 * first, which means page 1 holds the tail of this array.
	 *
	 * @param int $count Number of comments to create.
	 * @return array {
	 *     @type int   $post_id  Post ID.
	 *     @type int[] $comments Comment IDs, from the newest to the oldest.
	 * }
	 */
	private function create_post_with_comments( $count = 6 ) {
		$p   = self::factory()->post->create();
		$now = time();

		$comments = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$comments[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - $i ),
					'comment_author'   => 'Commenter ' . $i,
				)
			);
		}

		return array(
			'post_id'  => $p,
			'comments' => $comments,
		);
	}

	/**
	 * Returns the IDs of the comments rendered by wp_list_comments().
	 *
	 * @param string $output The rendered comment list.
	 * @return int[]
	 */
	private function get_rendered_comment_ids( $output ) {
		preg_match_all( '|id="comment\-([0-9]+)"|', $output, $matches );

		return array_map( 'intval', $matches[1] );
	}

	/**
	 * Runs a callback while recording the arguments of every comment query.
	 *
	 * @param callable $callback Callback that renders a comment list.
	 * @return array {
	 *     @type array $queries Recorded query vars.
	 *     @type array $recorded First recorded query vars, or an empty array.
	 * }
	 */
	private function record_comment_queries( $callback ) {
		$queries = array();
		$filter  = static function ( $comment_data, $query ) use ( &$queries ) {
			$queries[] = $query->query_vars;
			return $comment_data;
		};

		add_filter( 'comments_pre_query', $filter, 10, 2 );
		$output = call_user_func( $callback );
		remove_filter( 'comments_pre_query', $filter, 10 );

		return array(
			'output'  => $output,
			'queries' => $queries,
		);
	}

	/**
	 * Returns the first recorded query that paginated, or an empty array.
	 *
	 * A threaded query fetches descendants with a second, unpaginated query, so
	 * the paginated one cannot simply be assumed to be the first.
	 *
	 * @param array $queries Recorded query vars.
	 * @return array Query vars of the first paginated query, or an empty array.
	 */
	private function get_paginated_query( $queries ) {
		foreach ( $queries as $query_vars ) {
			if ( ! empty( $query_vars['number'] ) ) {
				return $query_vars;
			}
		}

		return array();
	}

	/**
	 * @ticket 35432
	 */
	public function test_custom_pagination_should_limit_the_fallback_comment_query() {
		$data     = $this->create_post_with_comments();
		$comments = $data['comments'];

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );
		update_option( 'thread_comments', true );
		update_option( 'thread_comments_depth', 5 );

		$this->go_to( get_permalink( $data['post_id'] ) );

		// comments_template() populates $wp_query->comments.
		get_echo( 'comments_template' );

		$result = $this->record_comment_queries(
			static function () {
				return wp_list_comments(
					array(
						'echo' => false,
						'page' => 2,
					)
				);
			}
		);

		$query_vars = $this->get_paginated_query( $result['queries'] );

		$this->assertNotEmpty( $query_vars, 'The fallback should have run a paginated comment query.' );
		$this->assertSame( 2, $query_vars['number'], 'The fallback query should only fetch one page of comments.' );
		$this->assertSame( 2, $query_vars['offset'], 'The fallback query should offset to the requested page.' );
		$this->assertTrue( $query_vars['hierarchical'], 'Threaded display should paginate by top level comment.' );

		$this->assertSame( array( $comments[3], $comments[2] ), $this->get_rendered_comment_ids( $result['output'] ) );
	}

	/**
	 * @ticket 35432
	 */
	public function test_custom_pagination_should_render_the_requested_page() {
		$data     = $this->create_post_with_comments();
		$comments = $data['comments'];

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );

		$this->go_to( get_permalink( $data['post_id'] ) );

		get_echo( 'comments_template' );

		// Comments paginate oldest first, so page 1 holds the oldest comments.
		$pages = array(
			1 => array( $comments[5], $comments[4] ),
			2 => array( $comments[3], $comments[2] ),
			3 => array( $comments[1], $comments[0] ),
		);

		foreach ( $pages as $page => $expected ) {
			$found = wp_list_comments(
				array(
					'echo' => false,
					'page' => $page,
				)
			);

			$this->assertSame( $expected, $this->get_rendered_comment_ids( $found ), "Failed asserting the comments of page $page." );
		}
	}

	/**
	 * @ticket 35432
	 */
	public function test_custom_pagination_should_render_the_children_of_a_threaded_page() {
		$data     = $this->create_post_with_comments( 5 );
		$comments = $data['comments'];

		// A child of the comment at index 2, which is on page 2 with a page size of 2.
		$child = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $data['post_id'],
				'comment_parent'   => $comments[2],
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - 10 ),
			)
		);

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );
		update_option( 'thread_comments', true );
		update_option( 'thread_comments_depth', 5 );

		$this->go_to( get_permalink( $data['post_id'] ) );

		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'echo' => false,
				'page' => 2,
			)
		);

		$this->assertSame(
			array( $comments[2], $child, $comments[1] ),
			$this->get_rendered_comment_ids( $found ),
			'A threaded page should render the children of its top level comments.'
		);
	}

	/**
	 * @ticket 35432
	 */
	public function test_custom_pagination_should_paginate_over_the_requested_comment_type() {
		$data     = $this->create_post_with_comments();
		$comments = $data['comments'];
		$p        = $data['post_id'];
		$now      = time();

		// Four pings, older than the regular comments.
		$pings = array();
		for ( $i = 0; $i < 4; $i++ ) {
			$pings[] = self::factory()->comment->create(
				array(
					'comment_post_ID'  => $p,
					'comment_type'     => 0 === $i % 2 ? 'pingback' : 'trackback',
					'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 - $i ),
					'comment_author'   => 'Pinger ' . $i,
				)
			);
		}

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );

		$this->go_to( get_permalink( $p ) );

		get_echo( 'comments_template' );

		// Page 2 of the pings, oldest first, so the newest pings land on it.
		$found = wp_list_comments(
			array(
				'echo' => false,
				'page' => 2,
				'type' => 'pings',
			)
		);
		$this->assertSame( array( $pings[1], $pings[0] ), $this->get_rendered_comment_ids( $found ) );

		// Page 2 of the comments is unaffected by the pings.
		$found = wp_list_comments(
			array(
				'echo' => false,
				'page' => 2,
				'type' => 'comment',
			)
		);
		$this->assertSame( array( $comments[3], $comments[2] ), $this->get_rendered_comment_ids( $found ) );
	}

	/**
	 * @ticket 35432
	 */
	public function test_custom_pagination_should_respect_reverse_top_level() {
		$data     = $this->create_post_with_comments();
		$comments = $data['comments'];

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );

		$this->go_to( get_permalink( $data['post_id'] ) );

		get_echo( 'comments_template' );

		$found = wp_list_comments(
			array(
				'echo'              => false,
				'page'              => 2,
				'reverse_top_level' => true,
			)
		);

		$this->assertSame( array( $comments[2], $comments[3] ), $this->get_rendered_comment_ids( $found ) );

		$found = wp_list_comments(
			array(
				'echo'              => false,
				'page'              => 2,
				'reverse_top_level' => false,
			)
		);

		$this->assertSame( array( $comments[3], $comments[2] ), $this->get_rendered_comment_ids( $found ) );
	}

	/**
	 * @ticket 35432
	 */
	public function test_explicit_comments_should_bypass_the_fallback_comment_query() {
		$data     = $this->create_post_with_comments();
		$comments = $data['comments'];

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'oldest' );

		$this->go_to( get_permalink( $data['post_id'] ) );

		get_echo( 'comments_template' );

		$explicit = array( get_comment( $comments[1] ), get_comment( $comments[3] ) );

		$result = $this->record_comment_queries(
			static function () use ( $explicit ) {
				return wp_list_comments(
					array(
						'echo'     => false,
						'page'     => 2,
						'per_page' => 1,
					),
					$explicit
				);
			}
		);

		$this->assertSame( array(), $result['queries'], 'An explicit comment array should not trigger a comment query.' );
		$this->assertSame( array( $comments[3] ), $this->get_rendered_comment_ids( $result['output'] ) );
	}

	/**
	 * @ticket 35432
	 */
	public function test_custom_pagination_without_a_page_should_render_the_last_page() {
		$data     = $this->create_post_with_comments();
		$comments = $data['comments'];

		update_option( 'page_comments', true );
		update_option( 'comments_per_page', 2 );
		update_option( 'default_comments_page', 'newest' );

		$this->go_to( get_permalink( $data['post_id'] ) );

		get_echo( 'comments_template' );

		// No 'page' is passed, so the last page of a 'newest' first list is shown.
		$found = wp_list_comments(
			array(
				'echo'     => false,
				'per_page' => 2,
			)
		);

		$this->assertSame( array( $comments[1], $comments[0] ), $this->get_rendered_comment_ids( $found ) );
	}
}
