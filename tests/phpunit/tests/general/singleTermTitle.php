<?php

/**
 * Tests for the `single_term_title()` function.
 *
 * @group general
 * @group template
 * @group taxonomy
 *
 * @covers ::single_term_title
 */
class Tests_General_SingleTermTitle extends WP_UnitTestCase {

	/**
	 * Category ID.
	 *
	 * @var int
	 */
	private static $category_id;

	/**
	 * Tag ID.
	 *
	 * @var int
	 */
	private static $tag_id;

	/**
	 * Custom taxonomy term ID.
	 *
	 * @var int
	 */
	private static $term_id;

	/**
	 * Author ID.
	 *
	 * @var int
	 */
	private static $author_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		register_taxonomy( 'wptests_genre', 'post', array( 'public' => true ) );

		self::$category_id = $factory->category->create( array( 'name' => 'News & Notes' ) );
		self::$tag_id      = $factory->tag->create( array( 'name' => 'Featured' ) );
		self::$term_id     = $factory->term->create(
			array(
				'taxonomy' => 'wptests_genre',
				'name'     => 'Jazz',
			)
		);
		self::$author_id   = $factory->user->create( array( 'role' => 'author' ) );

		$post_id = $factory->post->create(
			array(
				'post_author'   => self::$author_id,
				'post_category' => array( self::$category_id ),
				'tags_input'    => array( 'Featured' ),
			)
		);
		wp_set_object_terms( $post_id, self::$term_id, 'wptests_genre' );
	}

	public function set_up() {
		parent::set_up();

		register_taxonomy( 'wptests_genre', 'post', array( 'public' => true ) );
		register_post_type(
			'wptests_book',
			array(
				'public'      => true,
				'has_archive' => true,
			)
		);
	}

	/**
	 * Goes to an archive.
	 *
	 * @param string $archive Archive type: 'category', 'tag', 'tax', 'author' or 'post_type'.
	 */
	private function go_to_archive( $archive ) {
		$links = array(
			'category'  => get_category_link( self::$category_id ),
			'tag'       => get_tag_link( self::$tag_id ),
			'tax'       => get_term_link( self::$term_id ),
			'author'    => get_author_posts_url( self::$author_id ),
			'post_type' => get_post_type_archive_link( 'wptests_book' ),
		);

		$this->go_to( $links[ $archive ] );
	}

	/**
	 * Tests the returned title for each term archive.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_term_archives
	 *
	 * @param string $archive  Archive type.
	 * @param string $expected Expected title.
	 */
	public function test_returns_title_for_term_archives( $archive, $expected ) {
		$this->go_to_archive( $archive );

		$this->assertSame( $expected, single_term_title( 'Archive: ', false ) );
	}

	/**
	 * Data provider for test_returns_title_for_term_archives().
	 *
	 * @return array[]
	 */
	public static function data_term_archives() {
		return array(
			'category'        => array( 'category', 'Archive: News &amp; Notes' ),
			'tag'             => array( 'tag', 'Archive: Featured' ),
			'custom taxonomy' => array( 'tax', 'Archive: Jazz' ),
		);
	}

	/**
	 * Tests that the title is displayed by default.
	 *
	 * @ticket 65819
	 */
	public function test_displays_title_by_default() {
		$this->go_to_archive( 'tag' );

		$this->expectOutputString( 'Featured' );

		$this->assertNull( single_term_title() );
	}

	/**
	 * Tests that the prefix is displayed before the title.
	 *
	 * @ticket 65819
	 */
	public function test_displays_prefix() {
		$this->go_to_archive( 'tax' );

		$this->expectOutputString( 'Genre: Jazz' );

		single_term_title( 'Genre: ' );
	}

	/**
	 * Tests that null is returned and nothing is displayed outside of term archives.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_not_term_archives
	 *
	 * @param string $archive Archive type, or an empty string for the home page.
	 */
	public function test_returns_null_outside_term_archives( $archive ) {
		if ( $archive ) {
			$this->go_to_archive( $archive );
		} else {
			$this->go_to( home_url( '/' ) );
		}

		$this->expectOutputString( '' );

		$this->assertNull( single_term_title( 'Archive: ' ), 'Displaying should return null.' );
		$this->assertNull( single_term_title( 'Archive: ', false ), 'Returning should return null.' );
	}

	/**
	 * Data provider for test_returns_null_outside_term_archives().
	 *
	 * @return array[]
	 */
	public static function data_not_term_archives() {
		return array(
			'home page'         => array( '' ),
			'author archive'    => array( 'author' ),
			'post type archive' => array( 'post_type' ),
		);
	}

	/**
	 * Tests that each archive type applies only its own filter, and the filtered title is used.
	 *
	 * @ticket 65819
	 *
	 * @dataProvider data_filters
	 *
	 * @param string $archive  Archive type.
	 * @param string $hook     Filter expected to run.
	 * @param string $name     Raw term name passed to the filter.
	 * @param string $expected Expected filtered title.
	 */
	public function test_applies_filter_for_archive_type( $archive, $hook, $name, $expected ) {
		$filters = array();
		foreach ( array( 'single_cat_title', 'single_tag_title', 'single_term_title' ) as $filter_hook ) {
			$filters[ $filter_hook ] = new MockAction();
			add_filter( $filter_hook, array( $filters[ $filter_hook ], 'filter' ) );
		}

		add_filter(
			$hook,
			static function ( $title ) {
				return strtoupper( $title );
			},
			20
		);

		$this->go_to_archive( $archive );

		$this->assertSame( $expected, single_term_title( '', false ), 'The filtered title should be returned.' );

		foreach ( $filters as $filter_hook => $filter ) {
			if ( $filter_hook === $hook ) {
				$this->assertSame( array( $name ), $filter->get_args()[0], "The $filter_hook filter should receive the term name." );
			} else {
				$this->assertSame( 0, $filter->get_call_count(), "The $filter_hook filter should not run." );
			}
		}
	}

	/**
	 * Data provider for test_applies_filter_for_archive_type().
	 *
	 * @return array[]
	 */
	public static function data_filters() {
		return array(
			'category'        => array( 'category', 'single_cat_title', 'News &amp; Notes', 'NEWS &AMP; NOTES' ),
			'tag'             => array( 'tag', 'single_tag_title', 'Featured', 'FEATURED' ),
			'custom taxonomy' => array( 'tax', 'single_term_title', 'Jazz', 'JAZZ' ),
		);
	}

	/**
	 * Tests that an empty filtered title returns null and displays nothing.
	 *
	 * @ticket 65819
	 */
	public function test_empty_filtered_title_returns_null() {
		add_filter( 'single_cat_title', '__return_empty_string' );

		$this->go_to_archive( 'category' );

		$this->expectOutputString( '' );

		$this->assertNull( single_term_title( 'Archive: ' ), 'Displaying should return null.' );
		$this->assertNull( single_term_title( 'Archive: ', false ), 'Returning should return null.' );
	}
}
