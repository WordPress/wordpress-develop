<?php

/**
 * Tests for the `single_post_title()` function.
 *
 * @group general
 * @group template
 */
class Tests_General_SinglePostTitle extends WP_UnitTestCase {

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	private static $post_id;

	/**
	 * Page ID.
	 *
	 * @var int
	 */
	private static $page_id;

	/**
	 * Category ID.
	 *
	 * @var int
	 */
	private static $category_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_id = $factory->category->create( array( 'name' => 'News' ) );
		self::$post_id     = $factory->post->create(
			array(
				'post_title'    => 'Hello Title',
				'post_category' => array( self::$category_id ),
			)
		);
		self::$page_id     = $factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'About Page',
			)
		);
	}

	/**
	 * Tests the title with and without a prefix on singular views.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_post_title
	 *
	 * @dataProvider data_singular_titles
	 *
	 * @param string $post_type Post type to view.
	 * @param string $prefix    Prefix passed to the function.
	 * @param string $expected  Expected title.
	 */
	public function test_returns_title_on_singular_views( $post_type, $prefix, $expected ) {
		$this->go_to( get_permalink( 'page' === $post_type ? self::$page_id : self::$post_id ) );

		$this->assertSame( $expected, single_post_title( $prefix, false ) );
	}

	/**
	 * Data provider for test_returns_title_on_singular_views().
	 *
	 * @return array[]
	 */
	public static function data_singular_titles() {
		return array(
			'post, no prefix'   => array( 'post', '', 'Hello Title' ),
			'post, with prefix' => array( 'post', 'Blog &raquo; ', 'Blog &raquo; Hello Title' ),
			'page, with prefix' => array( 'page', 'Site: ', 'Site: About Page' ),
		);
	}

	/**
	 * Tests that the title is echoed by default.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_post_title
	 */
	public function test_echoes_title_by_default() {
		$this->go_to( get_permalink( self::$post_id ) );

		$this->expectOutputString( 'Blog: Hello Title' );

		$this->assertNull( single_post_title( 'Blog: ' ) );
	}

	/**
	 * Tests that null is returned when the queried object is not a post.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_post_title
	 */
	public function test_returns_null_without_queried_post() {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->expectOutputString( '' );

		$this->assertNull( single_post_title( 'Blog: ', true ) );
	}

	/**
	 * Tests that null is returned when there is no queried object.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_post_title
	 */
	public function test_returns_null_on_home() {
		$this->go_to( home_url( '/' ) );

		$this->assertNull( single_post_title( '', false ) );
	}

	/**
	 * Tests that the 'single_post_title' filter receives the title and post, and its result is used.
	 *
	 * @ticket 65819
	 *
	 * @covers ::single_post_title
	 */
	public function test_single_post_title_filter() {
		$this->go_to( get_permalink( self::$post_id ) );

		$received = array();

		add_filter(
			'single_post_title',
			static function ( $title, $post ) use ( &$received ) {
				$received[] = array( $title, $post->ID );

				return $title . '-filtered';
			},
			10,
			2
		);

		$this->assertSame( 'Blog: Hello Title-filtered', single_post_title( 'Blog: ', false ) );
		$this->assertSame( array( array( 'Hello Title', self::$post_id ) ), $received, 'The filter should run once with the title and the queried post.' );
	}
}
