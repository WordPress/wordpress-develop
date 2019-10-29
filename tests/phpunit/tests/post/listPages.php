<?php

/**
 * @group post
 */
class Tests_List_Pages extends WP_UnitTestCase {
	/**
	 * Author user id.
	 *
	 * @var int
	 */
	public static $author;

	/**
	 * Parent page id.
	 *
	 * @var int
	 */
	public static $parent_1;

	/**
	 * Parent page id.
	 *
	 * @var int
	 */
	public static $parent_2;

	/**
	 * Parent page id.
	 *
	 * @var int
	 */
	public static $parent_3;

	/**
	 * Child page ids.
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

	public static function wpSetupBeforeClass() {
		self::$time = time();

		$post_date = gmdate( 'Y-m-d H:i:s', self::$time );

		self::$parent_1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent 1',
				'post_date'  => $post_date,
			)
		);

		self::$author = self::factory()->user->create( array( 'role' => 'author' ) );

		self::$parent_2 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Parent 2',
				'post_date'  => $post_date,
			)
		);

		self::$parent_3 = self::factory()->post->create(
			array(
				'post_author' => self::$author,
				'post_type'   => 'page',
				'post_title'  => 'Parent 3',
				'post_date'   => $post_date,
			)
		);

		foreach ( array( self::$parent_1, self::$parent_2, self::$parent_3 ) as $page ) {
			self::$children[ $page ][] = self::factory()->post->create(
				array(
					'post_parent' => $page,
					'post_type'   => 'page',
					'post_title'  => 'Child 1',
					'post_date'   => $post_date,
				)
			);
			self::$children[ $page ][] = self::factory()->post->create(
				array(
					'post_parent' => $page,
					'post_type'   => 'page',
					'post_title'  => 'Child 2',
					'post_date'   => $post_date,
				)
			);
			self::$children[ $page ][] = self::factory()->post->create(
				array(
					'post_parent' => $page,
					'post_type'   => 'page',
					'post_title'  => 'Child 3',
					'post_date'   => $post_date,
				)
			);
		}
	}

	function test_wp_list_pages_default() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_depth() {
		$args = array(
			'echo'  => false,
			'depth' => 1,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_show_date() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_date_format() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_child_of() {
		$args = array(
			'echo'     => false,
			'child_of' => self::$parent_2,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li>
<li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li>
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_exclude() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_title_li() {
		$args = array(
			'echo'     => false,
			'depth'    => 1,
			'title_li' => 'PageTitle',
		);

		$expected = '<li class="pagenav">PageTitle<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a></li>
<li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_echo() {
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

	function test_wp_list_pages_authors() {
		$args = array(
			'echo'    => false,
			'authors' => self::$author,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_3 . '"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_number() {
		$args = array(
			'echo'   => false,
			'number' => 1,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li>
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_sort_column() {
		$args = array(
			'echo'        => false,
			'sort_column' => 'post_author',
			'sort_order'  => 'DESC',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a>
<ul class=\'children\'>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a></li>
	<li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a></li>
</ul>
</li>
<li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a>
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
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_link_before() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_link_after() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}


	function test_wp_list_pages_include() {
		$args = array(
			'echo'    => false,
			'include' => self::$parent_1 . ',' . self::$parent_3,
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . '"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a></li>
<li class="page_item page-item-' . self::$parent_3 . '"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a></li>
</ul></li>';

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_exclude_tree() {
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

		$this->assertEqualsIgnoreEOL( $expected, wp_list_pages( $args ) );
	}

	function test_wp_list_pages_discarded_whitespace() {
		$args = array(
			'echo'         => false,
			'item_spacing' => 'discard',
		);

		$expected = '<li class="pagenav">Pages<ul><li class="page_item page-item-' . self::$parent_1 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_1 ) . '">Parent 1</a><ul class=\'children\'><li class="page_item page-item-' . self::$children[ self::$parent_1 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][0] ) . '">Child 1</a></li><li class="page_item page-item-' . self::$children[ self::$parent_1 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][1] ) . '">Child 2</a></li><li class="page_item page-item-' . self::$children[ self::$parent_1 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_1 ][2] ) . '">Child 3</a></li></ul></li><li class="page_item page-item-' . self::$parent_2 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_2 ) . '">Parent 2</a><ul class=\'children\'><li class="page_item page-item-' . self::$children[ self::$parent_2 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][0] ) . '">Child 1</a></li><li class="page_item page-item-' . self::$children[ self::$parent_2 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][1] ) . '">Child 2</a></li><li class="page_item page-item-' . self::$children[ self::$parent_2 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_2 ][2] ) . '">Child 3</a></li></ul></li><li class="page_item page-item-' . self::$parent_3 . ' page_item_has_children"><a href="' . get_permalink( self::$parent_3 ) . '">Parent 3</a><ul class=\'children\'><li class="page_item page-item-' . self::$children[ self::$parent_3 ][0] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][0] ) . '">Child 1</a></li><li class="page_item page-item-' . self::$children[ self::$parent_3 ][1] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][1] ) . '">Child 2</a></li><li class="page_item page-item-' . self::$children[ self::$parent_3 ][2] . '"><a href="' . get_permalink( self::$children[ self::$parent_3 ][2] ) . '">Child 3</a></li></ul></li></ul></li>';

		$this->assertEquals( $expected, wp_list_pages( $args ) );
	}
}
