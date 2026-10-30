<?php

/**
 * Tests for the `get_the_post_navigation()` and `the_post_navigation()` functions.
 *
 * @group link
 *
 * @covers ::get_the_post_navigation
 * @covers ::the_post_navigation
 */
class Tests_Link_GetThePostNavigation extends WP_UnitTestCase {

	/**
	 * Post IDs for navigation tests.
	 *
	 * @var int[]
	 */
	protected static $post_ids = array();

	/**
	 * Sets up posts with sequential dates.
	 *
	 * @param WP_UnitTest_Factory $factory Unit test factory.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'First Post',
				'post_date'  => '2025-01-01 10:00:00',
			)
		);

		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'Middle Post',
				'post_date'  => '2025-01-02 10:00:00',
			)
		);

		self::$post_ids[] = $factory->post->create(
			array(
				'post_title' => 'Latest Post',
				'post_date'  => '2025-01-03 10:00:00',
			)
		);
	}

	/**
	 * Resets the global post after each test.
	 */
	public function tear_down() {
		$GLOBALS['post'] = null;
		parent::tear_down();
	}

	/**
	 * Sets up the query and global post.
	 *
	 * @param int $post_id Post ID.
	 */
	private function setup_post( $post_id ) {
		$this->go_to( get_permalink( $post_id ) );
	}

	/**
	 * Tests that an empty string is returned when no adjacent posts exist.
	 *
	 * @ticket 65819
	 */
	public function test_returns_empty_when_no_adjacent_posts() {
		$isolated_post = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);

		$this->setup_post( $isolated_post );

		$this->assertSame( '', get_the_post_navigation() );
	}

	/**
	 * Tests navigation links when both previous and next posts exist.
	 *
	 * @ticket 65819
	 */
	public function test_navigation_with_previous_and_next() {
		$this->setup_post( self::$post_ids[1] );

		$nav = get_the_post_navigation();

		$this->assertStringContainsString(
			'<div class="nav-previous"><a href="' . get_permalink( self::$post_ids[0] ) .
			'" rel="prev">First Post</a></div>',
			$nav
		);
		$this->assertStringContainsString(
			'<div class="nav-next"><a href="' . get_permalink( self::$post_ids[2] ) .
			'" rel="next">Latest Post</a></div>',
			$nav
		);
	}

	/**
	 * Tests navigation on the first post (only next post exists).
	 *
	 * @ticket 65819
	 */
	public function test_navigation_on_first_post() {
		$this->setup_post( self::$post_ids[0] );

		$nav = get_the_post_navigation();

		$this->assertStringNotContainsString( 'class="nav-previous"', $nav );
		$this->assertStringContainsString( 'class="nav-next"', $nav );
		$this->assertStringContainsString( 'Middle Post', $nav );
	}

	/**
	 * Tests navigation on the latest post (only previous post exists).
	 *
	 * @ticket 65819
	 */
	public function test_navigation_on_latest_post() {
		$this->setup_post( self::$post_ids[2] );

		$nav = get_the_post_navigation();

		$this->assertStringContainsString( 'class="nav-previous"', $nav );
		$this->assertStringContainsString( 'Middle Post', $nav );
		$this->assertStringNotContainsString( 'class="nav-next"', $nav );
	}

	/**
	 * Tests custom aria_label and screen_reader_text arguments.
	 *
	 * @ticket 65819
	 */
	public function test_custom_aria_label_and_screen_reader_text() {
		$this->setup_post( self::$post_ids[1] );

		$nav = get_the_post_navigation(
			array(
				'aria_label'         => 'Story navigation',
				'screen_reader_text' => 'Browse stories',
			)
		);

		$this->assertStringContainsString( 'aria-label="Story navigation"', $nav );
		$this->assertStringContainsString( '<h2 class="screen-reader-text">Browse stories</h2>', $nav );
	}

	/**
	 * Tests that aria_label falls back to screen_reader_text when empty.
	 *
	 * @ticket 65819
	 */
	public function test_aria_label_falls_back_to_screen_reader_text() {
		$this->setup_post( self::$post_ids[1] );

		$nav = get_the_post_navigation(
			array(
				'aria_label'         => '',
				'screen_reader_text' => 'Custom reader label',
			)
		);

		$this->assertStringContainsString( 'aria-label="Custom reader label"', $nav );
	}

	/**
	 * Tests custom class argument for the nav element.
	 *
	 * @ticket 65819
	 */
	public function test_custom_class() {
		$this->setup_post( self::$post_ids[1] );

		$nav = get_the_post_navigation(
			array(
				'class' => 'my-post-nav',
			)
		);

		$this->assertStringContainsString( 'class="navigation my-post-nav"', $nav );
	}

	/**
	 * Tests that the_post_navigation() outputs the markup.
	 *
	 * @ticket 65819
	 */
	public function test_the_post_navigation_echos_markup() {
		$this->setup_post( self::$post_ids[1] );

		$expected = get_the_post_navigation();

		$this->expectOutputString( $expected );
		the_post_navigation();
	}
}
