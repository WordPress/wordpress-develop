<?php

/**
 * @group link
 * @covers ::adjacent_post_link
 * @covers ::get_adjacent_post_link
 * @covers ::get_next_post_link
 * @covers ::get_previous_post_link
 * @covers ::next_post_link
 * @covers ::previous_post_link
 */
class Tests_Link_GetAdjacentPostLink extends WP_UnitTestCase {

	protected $post_ids;
	protected $cat_id;

	public function set_up() {
		parent::set_up();
		$this->cat_id     = self::factory()->category->create( array( 'name' => 'other' ) );
		$this->post_ids   = array();
		$this->post_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_date'   => '2014-10-26 05:32:29',
				'category_id' => 1,
			)
		);
		$this->post_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_date'   => '2014-10-26 04:32:29',
				'category_id' => $this->cat_id,
			)
		);
		$this->post_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_date'   => '2014-10-26 03:32:29',
				'category_id' => 1,
			)
		);
		$this->post_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_date'   => '2014-10-26 02:32:29',
				'category_id' => $this->cat_id,
			)
		);
		$this->post_ids[] = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_date'   => '2014-10-26 01:32:29',
				'category_id' => 1,
			)
		);

		// Set current post (has 2 on each end).
		global $GLOBALS;
		$GLOBALS['post'] = get_post( $this->post_ids[2] );
	}

	public function test_get_next_post_link_default() {
		$actual   = get_next_post_link();
		$title    = get_post( $this->post_ids[1] )->post_title;
		$expected = '<a href="' . home_url( '?p=' . $this->post_ids[1] ) . '" rel="next">' . $title . '</a> &raquo;';
		$this->assertSame( $expected, $actual );
	}

	public function test_get_previous_post_link_default() {
		$actual   = get_previous_post_link();
		$title    = get_post( $this->post_ids[3] )->post_title;
		$expected = '&laquo; <a href="' . home_url( '?p=' . $this->post_ids[3] ) . '" rel="prev">' . $title . '</a>';
		$this->assertSame( $expected, $actual );
	}

	public function test_get_next_post_link_same_category() {
		$actual   = get_next_post_link( '%link &raquo;', '%title', true );
		$title    = get_post( $this->post_ids[1] )->post_title;
		$expected = '<a href="' . home_url( '?p=' . $this->post_ids[1] ) . '" rel="next">' . $title . '</a> &raquo;';
		$this->assertSame( $expected, $actual );
	}

	public function test_get_previous_post_link_same_category() {
		$actual   = get_previous_post_link( '&laquo; %link', '%title', true );
		$title    = get_post( $this->post_ids[3] )->post_title;
		$expected = '&laquo; <a href="' . home_url( '?p=' . $this->post_ids[3] ) . '" rel="prev">' . $title . '</a>';
		$this->assertSame( $expected, $actual );
	}

	public function test_get_next_post_link_exclude_category() {
		$actual   = get_next_post_link( '%link &raquo;', '%title', false, $this->cat_id );
		$title    = get_post( $this->post_ids[1] )->post_title;
		$expected = '<a href="' . home_url( '?p=' . $this->post_ids[1] ) . '" rel="next">' . $title . '</a> &raquo;';
		$this->assertSame( $expected, $actual );
	}

	public function test_get_previous_post_link_exclude_category() {
		$actual   = get_previous_post_link( '&laquo; %link', '%title', false, $this->cat_id );
		$title    = get_post( $this->post_ids[3] )->post_title;
		$expected = '&laquo; <a href="' . home_url( '?p=' . $this->post_ids[3] ) . '" rel="prev">' . $title . '</a>';
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that adjacent_post_link() echoes get_adjacent_post_link().
	 *
	 * @ticket 65819
	 */
	public function test_adjacent_post_link_echoes_get_adjacent_post_link() {
		$previous_title = get_post( $this->post_ids[3] )->post_title;
		$expected_prev  = '&laquo; <a href="' . home_url( '?p=' . $this->post_ids[3] ) . '" rel="prev">' . $previous_title . '</a>';
		$actual_prev    = get_echo( 'adjacent_post_link', array( '&laquo; %link', '%title', false, '', true ) );

		$this->assertSame( $expected_prev, $actual_prev );

		$next_title    = get_post( $this->post_ids[1] )->post_title;
		$expected_next = '<a href="' . home_url( '?p=' . $this->post_ids[1] ) . '" rel="next">' . $next_title . '</a> &raquo;';
		$actual_next   = get_echo( 'adjacent_post_link', array( '%link &raquo;', '%title', false, '', false ) );

		$this->assertSame( $expected_next, $actual_next );
	}

	/**
	 * Tests that previous_post_link() echoes get_previous_post_link().
	 *
	 * @ticket 65819
	 */
	public function test_previous_post_link_echoes_get_previous_post_link() {
		$title    = get_post( $this->post_ids[3] )->post_title;
		$expected = '&laquo; <a href="' . home_url( '?p=' . $this->post_ids[3] ) . '" rel="prev">' . $title . '</a>';
		$actual   = get_echo( 'previous_post_link', array( '&laquo; %link', '%title' ) );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that next_post_link() echoes get_next_post_link().
	 *
	 * @ticket 65819
	 */
	public function test_next_post_link_echoes_get_next_post_link() {
		$title    = get_post( $this->post_ids[1] )->post_title;
		$expected = '<a href="' . home_url( '?p=' . $this->post_ids[1] ) . '" rel="next">' . $title . '</a> &raquo;';
		$actual   = get_echo( 'next_post_link', array( '%link &raquo;', '%title' ) );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Tests that adjacent post link display functions output nothing when on terminal posts.
	 *
	 * @ticket 65819
	 */
	public function test_adjacent_post_links_output_nothing_on_terminal_posts() {
		// Move to first (most recent) post so there is no next post.
		$GLOBALS['post'] = get_post( $this->post_ids[0] );
		$this->assertSame( '', get_echo( 'next_post_link' ) );

		// Move to last (oldest) post so there is no previous post.
		$GLOBALS['post'] = get_post( end( $this->post_ids ) );
		$this->assertSame( '', get_echo( 'previous_post_link' ) );
	}
}
