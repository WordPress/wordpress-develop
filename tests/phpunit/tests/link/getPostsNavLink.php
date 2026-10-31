<?php

/**
 * Tests the get_posts_nav_link() and posts_nav_link() functions.
 *
 * @group link
 * @covers ::get_posts_nav_link
 * @covers ::posts_nav_link
 */
class Tests_Link_GetPostsNavLink extends WP_UnitTestCase {

	/**
	 * Whether the pagination global was set before the test.
	 *
	 * @var bool
	 */
	private $had_paged;

	/**
	 * The original pagination global.
	 *
	 * @var mixed
	 */
	private $original_paged;

	/**
	 * Set up test fixtures before class.
	 *
	 * @param WP_UnitTest_Factory $factory
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$factory->post->create_many( 3 );
	}

	/**
	 * Saves the incoming pagination global before each test.
	 */
	public function set_up() {
		parent::set_up();

		$this->had_paged      = array_key_exists( 'paged', $GLOBALS );
		$this->original_paged = $this->had_paged ? $GLOBALS['paged'] : null;
	}

	/**
	 * Restores the incoming pagination global after each test.
	 */
	public function tear_down() {
		if ( $this->had_paged ) {
			$GLOBALS['paged'] = $this->original_paged;
		} else {
			unset( $GLOBALS['paged'] );
		}

		parent::tear_down();
	}

	/**
	 * Tests get_posts_nav_link() returns an empty string on a singular view.
	 *
	 * @ticket 65819
	 */
	public function test_get_posts_nav_link_empty_when_singular() {
		global $wp_query, $paged;

		$paged                 = 2;
		$wp_query              = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => $paged,
			)
		);
		$wp_query->is_singular = true;

		$this->assertSame( '', get_posts_nav_link() );
	}

	/**
	 * Tests get_posts_nav_link() returns an empty string when total pages <= 1.
	 *
	 * @ticket 65819
	 */
	public function test_get_posts_nav_link_empty_when_single_page() {
		global $wp_query, $paged;

		$paged    = 1;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 10,
				'paged'          => 1,
			)
		);

		$this->assertSame( '', get_posts_nav_link() );
	}

	/**
	 * Tests get_posts_nav_link() on the first page of results.
	 *
	 * @ticket 65819
	 */
	public function test_get_posts_nav_link_first_page() {
		global $wp_query, $paged;

		$paged    = 1;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 1,
			)
		);

		$nav = get_posts_nav_link();

		$this->assertStringContainsString( 'Next Page &raquo;', $nav );
		$this->assertStringNotContainsString( 'Previous Page', $nav );
		$this->assertStringNotContainsString( '&#8212;', $nav );
	}

	/**
	 * Tests get_posts_nav_link() on a middle page of results.
	 *
	 * @ticket 65819
	 */
	public function test_get_posts_nav_link_middle_page() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$nav = get_posts_nav_link();

		$this->assertStringContainsString( '&laquo; Previous Page', $nav );
		$this->assertStringContainsString( 'Next Page &raquo;', $nav );
		$this->assertStringContainsString( '&#8212;', $nav );
	}

	/**
	 * Tests get_posts_nav_link() on the last page of results.
	 *
	 * @ticket 65819
	 */
	public function test_get_posts_nav_link_last_page() {
		global $wp_query, $paged;

		$paged    = 3;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 3,
			)
		);

		$nav = get_posts_nav_link();

		$this->assertStringContainsString( '&laquo; Previous Page', $nav );
		$this->assertStringNotContainsString( 'Next Page', $nav );
		$this->assertStringNotContainsString( '&#8212;', $nav );
	}

	/**
	 * Tests get_posts_nav_link() with custom separator and labels.
	 *
	 * @ticket 65819
	 */
	public function test_get_posts_nav_link_custom_labels_and_separator() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$nav = get_posts_nav_link(
			array(
				'sep'      => ' *** ',
				'prelabel' => 'Back',
				'nxtlabel' => 'Forward',
			)
		);

		$this->assertStringContainsString( 'Back', $nav );
		$this->assertStringContainsString( 'Forward', $nav );
		$this->assertStringContainsString( ' *** ', $nav );
	}

	/**
	 * Tests that posts_nav_link() echoes the navigation output.
	 *
	 * @ticket 65819
	 */
	public function test_posts_nav_link_echoes() {
		global $wp_query, $paged;

		$paged    = 2;
		$wp_query = new WP_Query(
			array(
				'post_type'      => 'post',
				'posts_per_page' => 1,
				'paged'          => 2,
			)
		);

		$expected = get_posts_nav_link();

		$this->expectOutputString( $expected );
		posts_nav_link();
	}
}
