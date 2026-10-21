<?php

/**
 * Tests for get_adjacent_post_rel_link(), adjacent_posts_rel_link(), and adjacent_posts_rel_link_wp_head().
 *
 * @group link
 * @covers ::get_adjacent_post_rel_link
 * @covers ::adjacent_posts_rel_link
 * @covers ::adjacent_posts_rel_link_wp_head
 */
class Tests_Link_GetAdjacentPostRelLink extends WP_UnitTestCase {

	/**
	 * Post IDs for testing.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	/**
	 * Set up test fixtures before class.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'First Post',
				'post_date'  => '2026-01-01 10:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'Second Post',
				'post_date'  => '2026-01-02 10:00:00',
			)
		);
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'Third Post',
				'post_date'  => '2026-01-03 10:00:00',
			)
		);
	}

	/**
	 * Tests get_adjacent_post_rel_link() for previous post link.
	 *
	 * @ticket 65819
	 */
	public function test_get_adjacent_post_rel_link_previous() {
		$this->go_to( get_permalink( self::$post_ids[1] ) );

		$link = get_adjacent_post_rel_link( '%title', false, '', true );

		$this->assertStringContainsString( "rel='prev'", $link );
		$this->assertStringContainsString( "title='First Post'", $link );
		$this->assertStringContainsString( "href='" . get_permalink( self::$post_ids[0] ) . "'", $link );
	}

	/**
	 * Tests get_adjacent_post_rel_link() for next post link.
	 *
	 * @ticket 65819
	 */
	public function test_get_adjacent_post_rel_link_next() {
		$this->go_to( get_permalink( self::$post_ids[1] ) );

		$link = get_adjacent_post_rel_link( '%title', false, '', false );

		$this->assertStringContainsString( "rel='next'", $link );
		$this->assertStringContainsString( "title='Third Post'", $link );
		$this->assertStringContainsString( "href='" . get_permalink( self::$post_ids[2] ) . "'", $link );
	}

	/**
	 * Tests get_adjacent_post_rel_link() returns null when no adjacent post exists.
	 *
	 * @ticket 65819
	 */
	public function test_get_adjacent_post_rel_link_null_when_no_adjacent_post() {
		// First post has no previous post.
		$this->go_to( get_permalink( self::$post_ids[0] ) );
		$this->assertNull( get_adjacent_post_rel_link( '%title', false, '', true ) );

		// Third post has no next post.
		$this->go_to( get_permalink( self::$post_ids[2] ) );
		$this->assertNull( get_adjacent_post_rel_link( '%title', false, '', false ) );
	}

	/**
	 * Tests get_adjacent_post_rel_link() with custom title format replacing %title and %date.
	 *
	 * @ticket 65819
	 */
	public function test_get_adjacent_post_rel_link_custom_title_format() {
		$this->go_to( get_permalink( self::$post_ids[1] ) );

		$link = get_adjacent_post_rel_link( 'Pre: %title on %date', false, '', true );

		$this->assertStringContainsString( "title='Pre: First Post on ", $link );
	}

	/**
	 * Tests that adjacent_posts_rel_link() echoes both previous and next rel links.
	 *
	 * @ticket 65819
	 */
	public function test_adjacent_posts_rel_link_echoes() {
		$this->go_to( get_permalink( self::$post_ids[1] ) );

		$expected_prev = get_adjacent_post_rel_link( '%title', false, '', true );
		$expected_next = get_adjacent_post_rel_link( '%title', false, '', false );

		$this->expectOutputString( $expected_prev . $expected_next );
		adjacent_posts_rel_link();
	}

	/**
	 * Tests adjacent_posts_rel_link_wp_head() outputs on single post but not on home page.
	 *
	 * @ticket 65819
	 */
	public function test_adjacent_posts_rel_link_wp_head() {
		$this->go_to( home_url( '/' ) );
		$this->expectOutputString( '' );
		adjacent_posts_rel_link_wp_head();
	}
}
