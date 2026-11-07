<?php
/**
 * Tests for the breadcrumbs of the archives of the Breadcrumbs block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::block_core_breadcrumbs_get_archive_breadcrumbs
 */
class Tests_Blocks_BreadcrumbsArchive extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		// Two posts are enough for a second page.
		update_option( 'posts_per_page', 1 );
	}

	/**
	 * Creates two posts, so that the archive has a second page.
	 *
	 * @param array $args Optional. The arguments of the posts. Default none.
	 */
	private function create_posts( $args = array() ) {
		self::factory()->post->create_many( 2, $args );
	}

	/**
	 * Tests the breadcrumbs of a year archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_year() {
		$this->create_posts( array( 'post_date' => '2020-03-15 10:00:00' ) );
		$this->go_to( get_year_link( 2020 ) );

		$this->assertSame( array( array( 'label' => 2020 ) ), $this->get_items() );
	}

	/**
	 * Tests the breadcrumbs of a month archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_month() {
		$this->create_posts( array( 'post_date' => '2020-03-15 10:00:00' ) );
		$this->go_to( get_month_link( 2020, 3 ) );

		$this->assertSame(
			array(
				array(
					'label' => 2020,
					'url'   => get_year_link( 2020 ),
				),
				array( 'label' => 'March' ),
			),
			$this->get_items()
		);
	}

	/**
	 * Tests the breadcrumbs of a day archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_day() {
		$this->create_posts( array( 'post_date' => '2020-03-15 10:00:00' ) );
		$this->go_to( get_day_link( 2020, 3, 15 ) );

		$this->assertSame(
			array(
				array(
					'label' => 2020,
					'url'   => get_year_link( 2020 ),
				),
				array(
					'label' => 'March',
					'url'   => get_month_link( 2020, 3 ),
				),
				array( 'label' => 15 ),
			),
			$this->get_items()
		);
	}

	/**
	 * Tests the breadcrumbs of a date archive that is paginated.
	 *
	 * @ticket 66590
	 *
	 * @dataProvider data_paged_dates
	 *
	 * @param string $path     The query string of the archive.
	 * @param array  $expected The expected items before the page number item.
	 */
	public function test_should_link_the_date_to_the_first_page_and_add_the_page_number_when_paginated( $path, $expected ) {
		$this->create_posts( array( 'post_date' => '2020-03-15 10:00:00' ) );
		$this->go_to( home_url( $path . '&paged=2' ) );

		$items = $this->get_items();
		$last  = array_pop( $items );

		$this->assertSame( array( 'label' => 'Page 2' ), $last );
		$this->assertCount( count( $expected ), $items );

		$current = end( $items );

		$this->assertSame( get_pagenum_link( 1 ), $current['url'], 'The current date should link to the first page.' );
		$this->assertSame( $expected, array_column( $items, 'label' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_paged_dates() {
		return array(
			'year'  => array( '?year=2020', array( 2020 ) ),
			'month' => array( '?year=2020&monthnum=3', array( 2020, 'March' ) ),
			'day'   => array( '?year=2020&monthnum=3&day=15', array( 2020, 'March', 15 ) ),
		);
	}

	/**
	 * Tests the breadcrumbs of a date archive with the plain permalinks.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_date_in_the_m_query_variable() {
		$this->create_posts( array( 'post_date' => '2020-11-05 10:00:00' ) );
		$this->go_to( home_url( '/?m=20201105' ) );

		$this->assertSame(
			array(
				array(
					'label' => '2020',
					'url'   => get_year_link( 2020 ),
				),
				array(
					'label' => 'November',
					'url'   => get_month_link( 2020, 11 ),
				),
				array( 'label' => 5 ),
			),
			$this->get_items()
		);
	}

	/**
	 * Tests the breadcrumbs of a category archive with its ancestors.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_category_with_its_ancestors() {
		$grandparent_id = self::factory()->category->create( array( 'name' => 'Grandparent' ) );
		$parent_id      = self::factory()->category->create(
			array(
				'name'   => 'Parent',
				'parent' => $grandparent_id,
			)
		);
		$child_id       = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent_id,
			)
		);

		$this->create_posts( array( 'post_category' => array( $child_id ) ) );
		$this->go_to( get_category_link( $child_id ) );

		$this->assertSame(
			array(
				array(
					'label' => 'Grandparent',
					'url'   => get_term_link( $grandparent_id, 'category' ),
				),
				array(
					'label' => 'Parent',
					'url'   => get_term_link( $parent_id, 'category' ),
				),
				array( 'label' => 'Child' ),
			),
			$this->get_items()
		);
	}

	/**
	 * Tests the breadcrumbs of a tag archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_tag() {
		$tag_id = self::factory()->tag->create( array( 'name' => 'A tag' ) );

		$this->create_posts( array( 'tags_input' => array( $tag_id ) ) );
		$this->go_to( get_tag_link( $tag_id ) );

		$this->assertSame( array( array( 'label' => 'A tag' ) ), $this->get_items() );
	}

	/**
	 * Tests the breadcrumbs of a term archive that is paginated.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_term_to_the_first_page_and_add_the_page_number_when_paginated() {
		$parent_id = self::factory()->category->create( array( 'name' => 'Parent' ) );
		$child_id  = self::factory()->category->create(
			array(
				'name'   => 'Child',
				'parent' => $parent_id,
			)
		);

		self::factory()->post->create_many( 3, array( 'post_category' => array( $child_id ) ) );

		$this->go_to( add_query_arg( 'paged', 3, get_category_link( $child_id ) ) );

		$items = $this->get_items();

		$this->assertSame( array( 'Parent', 'Child', 'Page 3' ), array_column( $items, 'label' ) );
		$this->assertSame( get_term_link( $parent_id, 'category' ), $items[0]['url'] );
		$this->assertSame( get_pagenum_link( 1 ), $items[1]['url'] );
		$this->assertArrayNotHasKey( 'url', $items[2] );
	}

	/**
	 * Tests the breadcrumbs of a post type archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_a_post_type_archive() {
		$this->register_book_post_type();
		$this->create_posts( array( 'post_type' => 'book' ) );

		$this->go_to( get_post_type_archive_link( 'book' ) );

		$this->assertSame( array( array( 'label' => 'All books' ) ), $this->get_items() );
	}

	/**
	 * Tests the filter of the title of the post type archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_filter_the_title_of_a_post_type_archive() {
		$this->register_book_post_type();
		$this->create_posts( array( 'post_type' => 'book' ) );

		add_filter(
			'post_type_archive_title',
			static function ( $title, $post_type ) {
				return "$title ($post_type)";
			},
			10,
			2
		);

		$this->go_to( get_post_type_archive_link( 'book' ) );

		$this->assertSame( array( array( 'label' => 'All books (book)' ) ), $this->get_items() );
	}

	/**
	 * Tests that the label of the post type is used when the filter gives an empty title.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_label_of_the_post_type_when_the_filtered_title_is_empty() {
		$this->register_book_post_type();
		$this->create_posts( array( 'post_type' => 'book' ) );

		add_filter( 'post_type_archive_title', '__return_empty_string' );

		$this->go_to( get_post_type_archive_link( 'book' ) );

		$this->assertSame( array( array( 'label' => 'All books' ) ), $this->get_items() );
	}

	/**
	 * Tests the breadcrumbs of a post type archive that is paginated.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_post_type_to_the_first_page_and_add_the_page_number_when_paginated() {
		$this->register_book_post_type();
		$this->create_posts( array( 'post_type' => 'book' ) );

		$this->go_to( add_query_arg( 'paged', 2, get_post_type_archive_link( 'book' ) ) );

		$items = $this->get_items();

		$this->assertSame( array( 'All books', 'Page 2' ), array_column( $items, 'label' ) );
		$this->assertSame( get_pagenum_link( 1 ), $items[0]['url'] );
	}

	/**
	 * Tests the breadcrumbs of the archive of several post types.
	 *
	 * @ticket 66590
	 */
	public function test_should_use_the_first_post_type_of_an_archive_of_several_post_types() {
		$this->register_book_post_type();
		$this->create_posts( array( 'post_type' => 'book' ) );

		$this->go_to( get_post_type_archive_link( 'book' ) );
		set_query_var( 'post_type', array( 'book', 'post' ) );

		$this->assertSame( array( array( 'label' => 'All books' ) ), $this->get_items() );
	}

	/**
	 * Tests the breadcrumbs of an author archive.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_the_breadcrumbs_of_an_author() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Jane Writer' ) );
		$this->create_posts( array( 'post_author' => $user_id ) );

		$this->go_to( get_author_posts_url( $user_id ) );

		$this->assertSame( array( array( 'label' => 'Jane Writer' ) ), $this->get_items() );
	}

	/**
	 * Tests the breadcrumbs of an author archive that is paginated.
	 *
	 * @ticket 66590
	 */
	public function test_should_link_the_author_to_the_first_page_and_add_the_page_number_when_paginated() {
		$user_id = self::factory()->user->create( array( 'display_name' => 'Jane Writer' ) );
		$this->create_posts( array( 'post_author' => $user_id ) );

		$this->go_to( add_query_arg( 'paged', 2, get_author_posts_url( $user_id ) ) );

		$items = $this->get_items();

		$this->assertSame( array( 'Jane Writer', 'Page 2' ), array_column( $items, 'label' ) );
		$this->assertSame( get_pagenum_link( 1 ), $items[0]['url'] );
	}

	/**
	 * Tests that a view without a queried object has no breadcrumbs.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_no_breadcrumbs_without_a_queried_object() {
		$this->go_to( '/' );

		$this->assertSame( array(), $this->get_items() );
		$this->assertSame( array(), $this->get_items_for( '/?s=search' ) );
	}

	/**
	 * Tests that a paginated view without a queried object has no breadcrumbs.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_no_breadcrumbs_for_a_paginated_view_without_a_queried_object() {
		$this->create_posts();

		$this->go_to( '/?paged=2' );

		$this->assertSame( array(), $this->get_items() );
	}

	/**
	 * Tests the page number of a view that is not an archive of a kind that is handled.
	 *
	 * @ticket 66590
	 */
	public function test_should_get_only_the_page_number_for_a_paginated_view_that_is_not_handled() {
		$post_id = self::factory()->post->create();

		$this->assertSame( array(), $this->get_items_for( get_permalink( $post_id ) ) );

		$this->go_to( add_query_arg( 'paged', 4, get_permalink( $post_id ) ) );

		$this->assertSame( array( array( 'label' => 'Page 4' ) ), $this->get_items() );
	}

	/**
	 * Returns the breadcrumbs after going to a URL.
	 *
	 * @param string $url The URL.
	 * @return array The items.
	 */
	private function get_items_for( $url ) {
		$this->go_to( $url );

		return $this->get_items();
	}

	/**
	 * Returns the breadcrumbs of the archive.
	 *
	 * @return array The items.
	 */
	private function get_items() {
		return block_core_breadcrumbs_get_archive_breadcrumbs();
	}

	/**
	 * Registers the post type with an archive that the tests use.
	 */
	private function register_book_post_type() {
		register_post_type(
			'book',
			array(
				'public'      => true,
				'has_archive' => true,
				'labels'      => array(
					'name'     => 'Books',
					'archives' => 'All books',
				),
			)
		);

		$this->set_permalink_structure( '/%postname%/' );
	}

	public function tear_down() {
		_unregister_post_type( 'book' );

		parent::tear_down();
	}
}
