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
	 * @ticket 35805
	 *
	 * With 'reverse_top_level' => true, page 1 should start with the newest
	 * comments: page numbering must follow the display order.
	 */
	public function test_paged_comments_should_follow_display_order_when_reversed() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 4; $i++ ) {
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

		// Page 1 of the reversed list contains the two newest comments.
		$found1 = wp_list_comments(
			array(
				'reverse_top_level' => true,
				'per_page'          => 2,
				'page'              => 1,
				'echo'              => false,
			)
		);
		preg_match_all( '|id="comment\-([0-9]+)"|', $found1, $matches );
		$this->assertSame( array( $comments[4], $comments[3] ), array_map( 'intval', $matches[1] ) );

		// Page 2 of the reversed list contains the next two older comments.
		$found2 = wp_list_comments(
			array(
				'reverse_top_level' => true,
				'per_page'          => 2,
				'page'              => 2,
				'echo'              => false,
			)
		);
		preg_match_all( '|id="comment\-([0-9]+)"|', $found2, $matches );
		$this->assertSame( array( $comments[2], $comments[1] ), array_map( 'intval', $matches[1] ) );
	}

	/**
	 * @ticket 35805
	 *
	 * Without reversal, paging is unchanged: page 1 shows the two oldest
	 * comments in chronological order.
	 */
	public function test_paged_comments_should_be_unchanged_when_not_reversed() {
		$p = self::factory()->post->create();

		$comments = array();
		$now      = time();
		for ( $i = 0; $i <= 4; $i++ ) {
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
				'reverse_top_level' => false,
				'per_page'          => 2,
				'page'              => 1,
				'echo'              => false,
			)
		);
		preg_match_all( '|id="comment\-([0-9]+)"|', $found, $matches );
		$this->assertSame( array( $comments[0], $comments[1] ), array_map( 'intval', $matches[1] ) );
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
}
