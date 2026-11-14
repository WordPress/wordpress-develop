<?php

/**
 * @group post
 */
class Tests_Post_wpListPages extends WP_UnitTestCase {
	/**
	 * Editor user ID.
	 *
	 * @var int
	 */
	public static $editor;

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	public static $author;

	/**
	 * Parent page ID.
	 *
	 * @var int
	 */
	public static $parent_1;

	/**
	 * Parent page ID.
	 *
	 * @var int
	 */
	public static $parent_2;

	/**
	 * Parent page ID.
	 *
	 * @var int
	 */
	public static $parent_3;

	/**
	 * Child page IDs.
	 *
	 * @var array
	 */
	public static $children = array();

	/**
	 * Current timestamp cache, so that it is consistent across posts.
	 *
	 * @var int
	 */
	public static $time;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$time = time();

		$post_date = gmdate( 'Y-m-d H:i:s', self::$time );

		self::$editor = $factory->user->create( array( 'role' => 'editor' ) );
		self::$author = $factory->user->create( array( 'role' => 'author' ) );

		self::$parent_1 = $factory->post->create(
			array(
				'post_author' => self::$editor,
				'post_type'   => 'page',
				'post_title'  => 'Parent 1',
				'post_date'   => $post_date,
			)
		);

		self::$parent_2 = $factory->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent 2',
				'post_date'  => $post_date,
			)
		);

		self::$parent_3 = $factory->post->create(
			array(
				'post_author' => self::$author,
				'post_type'   => 'page',
				'post_title'  => 'Parent 3',
				'post_date'   => $post_date,
			)
		);

		foreach ( array( self::$parent_1, self::$parent_2, self::$parent_3 ) as $page ) {
			self::$children[ $page ][] = $factory->post->create(
				array(
					'post_parent' => $page,
					'post_type'   => 'page',
					'post_title'  => 'Child 1',
					'post_date'   => $post_date,
				)
			);
			self::$children[ $page ][] = $factory->post->create(
				array(
					'post_parent' => $page,
					'post_type'   => 'page',
					'post_title'  => 'Child 2',
					'post_date'   => $post_date,
				)
			);
			self::$children[ $page ][] = $factory->post->create(
				array(
					'post_parent' => $page,
					'post_type'   => 'page',
					'post_title'  => 'Child 3',
					'post_date'   => $post_date,
				)
			);
		}
	}

	public function test_wp_list_pages_default() {
		$args = array(
			'echo' => false,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a></li>
</ul>
</li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_depth() {
		$args = array(
			'echo'  => false,
			'depth' => 1,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	/**
	 * @ticket 61749
	 */
	public function test_wp_list_pages_depth_equals_zero() {
		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a></li>
</ul>
</li>
</ul></li>';

		// Execute wp_list_pages() with a string to force calling wp_parse_args().
		ob_start();
		wp_list_pages( 'depth=0' );
		$output = ob_get_clean();

		// If depth equals 0, all levels should be displayed.
		$this->assertSameIgnoreEOL( $expected, $output );
	}

	public function test_wp_list_pages_show_date() {
		$args = array(
			'echo'      => false,
			'depth'     => 1,
			'show_date' => true,
		);
		$date = gmdate( get_option( 'date_format' ), self::$time );

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a> ' . $date . '</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a> ' . $date . '</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a> ' . $date . '</li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_date_format() {
		$args = array(
			'echo'        => false,
			'show_date'   => true,
			'date_format' => 'l, F j, Y',
		);
		$date = gmdate( $args['date_format'], self::$time );

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a> ' . $date . '
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a> ' . $date . '</li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a> ' . $date . '</li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a> ' . $date . '</li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a> ' . $date . '
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a> ' . $date . '</li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a> ' . $date . '</li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a> ' . $date . '</li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a> ' . $date . '
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a> ' . $date . '</li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a> ' . $date . '</li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a> ' . $date . '</li>
</ul>
</li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_child_of() {
		$args = array(
			'echo'     => false,
			'child_of' => self::$parent_2,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_exclude() {
		$args = array(
			'echo'    => false,
			'exclude' => self::$parent_2,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	/**
	 * @ticket 66052
	 *
	 * @dataProvider data_wp_list_pages_id_list_callbacks
	 *
	 * @param callable(int): (int|numeric-string) $callback Callback to cast each page ID with.
	 */
	public function test_wp_list_pages_exclude_should_accept_an_array_of_ids( callable $callback ): void {
		$args = array(
			'echo'    => false,
			'depth'   => 1,
			'exclude' => array_map( $callback, array( self::$parent_1, self::$parent_3 ) ),
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL(
			$expected,
			wp_list_pages( $args ) // @phpstan-ignore argument.type (Back-compat: numeric-string IDs are still accepted.)
		);
	}

	/**
	 * Ensures the `exclude` argument keeps accepting a comma-separated string of page IDs.
	 *
	 * @ticket 66052
	 *
	 * @dataProvider data_wp_list_pages_exclude_separators
	 *
	 * @param string $separator Separator to join the page IDs with.
	 */
	public function test_wp_list_pages_exclude_should_accept_a_comma_separated_string( string $separator ): void {
		$args = array(
			'echo'    => false,
			'depth'   => 1,
			'exclude' => implode( $separator, array( self::$parent_1, self::$parent_3 ) ),
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL(
			$expected,
			wp_list_pages( $args ) // @phpstan-ignore argument.type (Back-compat: a comma-separated string is still accepted.)
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: string }>
	 */
	public function data_wp_list_pages_exclude_separators(): array {
		return array(
			'comma'            => array( ',' ),
			'comma and spaces' => array( ' , ' ),
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: callable(int): (int|numeric-string) }>
	 */
	public function data_wp_list_pages_id_list_callbacks(): array {
		return array(
			'integers'        => array( 'intval' ),
			'numeric strings' => array(
				static function ( int $id ): string {
					return (string) $id;
				},
			),
		);
	}

	/**
	 * Ensures the 'wp_list_pages_excludes' filter keeps receiving an array of numeric strings.
	 *
	 * @ticket 66052
	 */
	public function test_wp_list_pages_excludes_filter_should_receive_numeric_strings(): void {
		$filter = new MockAction();
		add_filter( 'wp_list_pages_excludes', array( $filter, 'filter' ) );

		$args = array(
			'echo'    => false,
			'exclude' => ' ' . self::$parent_1 . ', ' . self::$parent_3 . ' ',
		);

		wp_list_pages( $args ); // @phpstan-ignore argument.type (Back-compat: a comma-separated string is still accepted.)

		$this->assertSame(
			array( array( (string) self::$parent_1, (string) self::$parent_3 ) ),
			$filter->get_args()[0]
		);
	}

	/**
	 * Ensures wp_page_menu() adds the static front page to an `exclude` given as an array or a string.
	 *
	 * @ticket 66052
	 *
	 * @covers ::wp_page_menu
	 *
	 * @dataProvider data_wp_page_menu_exclude_formats
	 *
	 * @param callable(int[]): (int[]|numeric-string[]|string) $format Callback to format the array of page IDs to exclude.
	 */
	public function test_wp_page_menu_should_exclude_front_page_with_exclude_arg( callable $format ): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$parent_2 );

		$filter = new MockAction();
		add_filter( 'wp_list_pages_excludes', array( $filter, 'filter' ) );

		$menu = wp_page_menu(
			array(
				'echo'      => false,
				'show_home' => true,
				'depth'     => 1,
				'exclude'   => $format( array( self::$parent_1 ) ),
			)
		);

		$this->assertSame(
			array( array( (string) self::$parent_1, (string) self::$parent_2 ) ),
			$filter->get_args()[0],
			'Expected the excluded page and the front page to be passed to the filter.'
		);
		$this->assertSameSets(
			array( self::$parent_3 ),
			$this->get_listed_page_ids( $menu ),
			'Expected only the page that is neither excluded nor the front page to be listed.'
		);
	}

	/**
	 * Ensures wp_page_menu() omits the static front page, which the Home link already represents, when no `exclude` argument is given.
	 *
	 * @ticket 66052
	 *
	 * @covers ::wp_page_menu
	 */
	public function test_wp_page_menu_should_exclude_front_page_without_exclude_arg(): void {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$parent_2 );

		$filter = new MockAction();
		add_filter( 'wp_list_pages_excludes', array( $filter, 'filter' ) );

		$menu = wp_page_menu(
			array(
				'echo'      => false,
				'show_home' => true,
				'depth'     => 1,
			)
		);

		$this->assertSame(
			array( array( (string) self::$parent_2 ) ),
			$filter->get_args()[0],
			'Expected the front page ID to be the only page excluded, since no exclude argument was given.'
		);
		$this->assertSameSets(
			array( self::$parent_1, self::$parent_3 ),
			$this->get_listed_page_ids( $menu ),
			'Expected every page except the front page to be listed, since the Home link represents it.'
		);
	}

	/**
	 * Ensures wp_page_menu() leaves the `exclude` argument alone when there is no Home link or no static front page.
	 *
	 * @ticket 66052
	 *
	 * @covers ::wp_page_menu
	 *
	 * @dataProvider data_wp_page_menu_without_front_page_exclusion
	 *
	 * @param bool   $show_home     Value for the `show_home` argument.
	 * @param string $show_on_front Value for the `show_on_front` option.
	 */
	public function test_wp_page_menu_should_not_exclude_front_page( bool $show_home, string $show_on_front ): void {
		update_option( 'show_on_front', $show_on_front );
		update_option( 'page_on_front', self::$parent_2 );

		$filter = new MockAction();
		add_filter( 'wp_list_pages_excludes', array( $filter, 'filter' ) );

		$menu = wp_page_menu(
			array(
				'echo'      => false,
				'show_home' => $show_home,
				'depth'     => 1,
				'exclude'   => array( self::$parent_1 ),
			)
		);

		$this->assertSame(
			array( array( (string) self::$parent_1 ) ),
			$filter->get_args()[0],
			'Expected only the given excluded page to be passed to the filter, without the front page appended.'
		);
		$this->assertSameSets(
			array( self::$parent_2, self::$parent_3 ),
			$this->get_listed_page_ids( $menu ),
			'Expected the page_on_front page to be listed, since no Home link represents it.'
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ show_home: bool, show_on_front: 'page'|'posts' }>
	 */
	public function data_wp_page_menu_without_front_page_exclusion(): array {
		return array(
			'no Home link, static front page' => array(
				'show_home'     => false,
				'show_on_front' => 'page',
			),
			'Home link, posts on front'       => array(
				'show_home'     => true,
				'show_on_front' => 'posts',
			),
		);
	}

	/**
	 * Gets the IDs of the pages listed in the given markup, from the `page-item-{ID}` class of each `LI` tag.
	 *
	 * @param string $html Markup of a list of pages.
	 * @return list<int> Page IDs.
	 */
	private function get_listed_page_ids( string $html ): array {
		$page_ids  = array();
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag( array( 'tag_name' => 'LI' ) ) ) {
			foreach ( $processor->class_list() as $class_name ) {
				if ( str_starts_with( $class_name, 'page-item-' ) ) {
					$page_ids[] = (int) substr( $class_name, strlen( 'page-item-' ) );
				}
			}
		}
		return $page_ids;
	}

	/**
	 * Data provider.
	 *
	 * @return array<non-falsy-string, array{ 0: callable(int[]): (int[]|numeric-string[]|string) }>
	 */
	public function data_wp_page_menu_exclude_formats(): array {
		return array(
			'array of integers'        => array( 'array_values' ),
			'array of numeric strings' => array(
				static function ( array $ids ): array {
					return array_map( 'strval', $ids );
				},
			),
			'comma-separated string'   => array(
				static function ( array $ids ): string {
					return implode( ',', $ids );
				},
			),
		);
	}

	public function test_wp_list_pages_title_li() {
		$args = array(
			'echo'     => false,
			'depth'    => 1,
			'title_li' => 'PageTitle',
		);

		$expected = '<li class="pagenav">PageTitle<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_echo() {
		$args = array(
			'echo'  => true,
			'depth' => 1,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';
		$expected = str_replace( "\r\n", "\n", $expected );
		$this->expectOutputString( $expected );
		wp_list_pages( $args );
	}

	public function test_wp_list_pages_authors() {
		$args = array(
			'echo'    => false,
			'authors' => self::$author,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_3 . '"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_number() {
		$args = array(
			'echo'        => false,
			'number'      => 1,
			'sort_column' => 'ID',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . '"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_sort_column() {
		$args = array(
			'echo'        => false,
			'depth'       => 1,
			'sort_column' => 'post_author',
			'sort_order'  => 'DESC',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
<li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_link_before() {
		$args = array(
			'echo'        => false,
			'link_before' => 'BEFORE',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">BEFOREParent 1</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">BEFOREChild 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">BEFOREChild 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">BEFOREChild 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">BEFOREParent 2</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">BEFOREChild 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">BEFOREChild 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">BEFOREChild 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">BEFOREParent 3</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">BEFOREChild 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">BEFOREChild 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">BEFOREChild 3</a></li>
</ul>
</li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_link_after() {
		$args = array(
			'echo'       => false,
			'link_after' => 'AFTER',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1AFTER</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1AFTER</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2AFTER</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3AFTER</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2AFTER</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1AFTER</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2AFTER</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3AFTER</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3AFTER</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1AFTER</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2AFTER</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3AFTER</a></li>
</ul>
</li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}


	public function test_wp_list_pages_include() {
		$args = array(
			'echo'    => false,
			'include' => self::$parent_1 . ',' . self::$parent_3,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . '"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_3 . '"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	/**
	 * @ticket 66052
	 *
	 * @dataProvider data_wp_list_pages_id_list_callbacks
	 *
	 * @param callable(int): (int|numeric-string) $callback Callback to cast each page ID with.
	 */
	public function test_wp_list_pages_include_should_accept_an_array_of_ids( callable $callback ): void {
		$args = array(
			'echo'    => false,
			'include' => array_map( $callback, array( self::$parent_1, self::$parent_3 ) ),
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . '"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_3 . '"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertSameIgnoreEOL(
			$expected,
			wp_list_pages( $args ) // @phpstan-ignore argument.type (Back-compat: numeric-string IDs are still accepted.)
		);
	}

	public function test_wp_list_pages_exclude_tree() {
		$args = array(
			'echo'         => false,
			'exclude_tree' => self::$parent_2 . ',' . self::$parent_3,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a></li>
</ul>
</li>
</ul></li>';

		$this->assertSameIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	public function test_wp_list_pages_discarded_whitespace() {
		$args = array(
			'echo'         => false,
			'item_spacing' => 'discard',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a>
<ul class=\'children\'><li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a>
<ul class=\'children\'><li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a>
<ul class=\'children\'><li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a></li>
</ul>
</li>
</ul></li>';
		$expected = str_replace( array( "\r\n", "\n", "\t" ), '', $expected );

		$this->assertSame( $expected, wp_list_pages( $args ) );
	}

	/**
	 * @ticket 17590
	 */
	public function test_wp_list_pages_classes_with_hierarchical_cpt() {
		$args = array(
			'echo'      => false,
			'post_type' => 'taco',
		);

		register_post_type(
			$args['post_type'],
			array(
				'hierarchical' => true,
				'public'       => true,
			)
		);

		$posts   = self::factory()->post->create_many( 2, array( 'post_type' => $args['post_type'] ) );
		$post_id = reset( $posts );

		$this->go_to( "/?p={$post_id}&post_type={$args['post_type']}" );

		$this->assertSame(
			$post_id,
			get_queried_object_id(),
			'The queried object ID should match the ID of the requested CPT item.'
		);

		$output = wp_list_pages( $args );

		_unregister_post_type( $args['post_type'] );

		$this->assertNotEmpty(
			$output,
			'The output should not be empty.'
		);

		$this->assertSame(
			2,
			substr_count( $output, 'class="page_item ' ),
			'The number of "page_item" classes should be equal to the total CPT items count.'
		);

		$this->assertStringContainsString(
			'current_page_item',
			$output,
			'The output should contain the "current_page_item" class.'
		);

		$this->assertSame(
			1,
			substr_count( $output, 'current_page_item' ),
			'The output should contain exactly one "current_page_item" class.'
		);
	}

	/**
	 * @ticket 28801
	 * @dataProvider data_wp_list_pages_omitted_ancestor
	 *
	 * @param int $depth Maximum depth to display.
	 */
	public function test_wp_list_pages_preserves_hierarchy_when_menu_order_places_child_first( $depth ) {
		$ancestor = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$parent   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $ancestor,
				'post_title'  => 'Included parent',
				'menu_order'  => 10,
			)
		);
		$child    = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_parent' => $parent,
				'post_title'  => 'Included child',
				'menu_order'  => 0,
			)
		);

		$expected = '<li class="page_item page-item-' . $parent . ' page_item_has_children"><a href="' . get_permalink( $parent ) . '">Included parent</a>';
		if ( 1 !== $depth ) {
			$expected .= '<ul class=\'children\'><li class="page_item page-item-' . $child . '"><a href="' . get_permalink( $child ) . '">Included child</a></li></ul>';
		}
		$expected .= '</li>';

		$this->assertSame(
			$expected,
			wp_list_pages(
				array(
					'include'      => array( $parent, $child ),
					'sort_column'  => 'menu_order',
					'depth'        => $depth,
					'echo'         => false,
					'title_li'     => '',
					'item_spacing' => 'discard',
				)
			)
		);
	}

	/**
	 * Data provider for test_wp_list_pages_preserves_hierarchy_when_menu_order_places_child_first().
	 *
	 * @return array
	 */
	public static function data_wp_list_pages_omitted_ancestor() {
		return array(
			'unlimited depth' => array( 0 ),
			'depth one'       => array( 1 ),
			'depth two'       => array( 2 ),
		);
	}
}
