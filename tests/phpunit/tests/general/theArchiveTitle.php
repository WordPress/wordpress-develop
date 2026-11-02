<?php

/**
 * Tests for the `the_archive_title()` function.
 *
 * @group general
 * @group template
 *
 * @covers ::the_archive_title
 */
class Tests_General_TheArchiveTitle extends WP_UnitTestCase {

	/**
	 * Category ID.
	 *
	 * @var int
	 */
	private static $category_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$category_id = $factory->category->create(
			array(
				'name' => 'News',
			)
		);
	}

	/**
	 * Tests that the_archive_title() displays the title on a category archive.
	 *
	 * @ticket 65819
	 */
	public function test_the_archive_title_should_display_on_category_archive() {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->expectOutputString( 'Category: <span>News</span>' );
		the_archive_title();
	}

	/**
	 * Tests that the_archive_title() outputs before and after markup when provided.
	 *
	 * @ticket 65819
	 */
	public function test_the_archive_title_should_output_before_and_after_markup() {
		$this->go_to( get_category_link( self::$category_id ) );

		$this->expectOutputString( '<h1>Category: <span>News</span></h1>' );
		the_archive_title( '<h1>', '</h1>' );
	}

	/**
	 * Tests that the_archive_title() outputs the title without prefix when prefix is filtered to empty.
	 *
	 * @ticket 65819
	 */
	public function test_the_archive_title_without_prefix() {
		add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

		$this->go_to( get_category_link( self::$category_id ) );

		$this->expectOutputString( 'News' );
		the_archive_title();
	}

	/**
	 * Tests that the_archive_title() produces no output when the archive title is empty.
	 *
	 * @ticket 65819
	 */
	public function test_the_archive_title_should_produce_no_output_when_title_is_empty() {
		add_filter( 'get_the_archive_title', '__return_empty_string' );

		$this->go_to( get_category_link( self::$category_id ) );

		$this->expectOutputString( '' );
		the_archive_title( '<h1>', '</h1>' );
	}
}
