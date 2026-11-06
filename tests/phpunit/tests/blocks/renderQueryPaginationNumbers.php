<?php
/**
 * Tests for the server-side rendering of the Query Pagination Numbers block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_query_pagination_numbers
 */
class Tests_Blocks_RenderQueryPaginationNumbers extends WP_UnitTestCase {

	/**
	 * Original block to render of the block supports.
	 *
	 * @var array|null
	 */
	private $original_block_to_render;

	/**
	 * The original value of the request data.
	 *
	 * @var array
	 */
	private $original_get;

	/**
	 * Whether the block was registered before the test.
	 *
	 * @var bool
	 */
	private $was_registered;

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render = WP_Block_Supports::$block_to_render;
		$this->original_get             = $_GET;
		$this->was_registered           = WP_Block_Type_Registry::get_instance()->is_registered( 'core/query-pagination-numbers' );

		if ( ! $this->was_registered ) {
			register_block_core_query_pagination_numbers();
		}

		// Five posts that a query finds, which are three pages of two.
		self::factory()->post->create_many( 5, array( 'post_title' => 'Pager post' ) );
		update_option( 'posts_per_page', 2 );
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$_GET                               = $this->original_get;

		if ( ! $this->was_registered ) {
			unregister_block_type( 'core/query-pagination-numbers' );
		}

		parent::tear_down();
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Optional. Context of the block, which has a query for the posts with the title. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes = array(), $context = array() ) {
		$parsed_block = array(
			'blockName'    => 'core/query-pagination-numbers',
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);

		if ( ! isset( $context['query'] ) ) {
			$context['query'] = array();
		}

		$context['query'] = array_merge(
			array(
				'perPage'  => 2,
				'postType' => 'post',
				'search'   => 'Pager post',
				'inherit'  => false,
			),
			$context['query']
		);

		$block = new WP_Block( $parsed_block, $context );

		WP_Block_Supports::$block_to_render = $parsed_block;

		return render_block_core_query_pagination_numbers( $attributes, '', $block );
	}

	/**
	 * Returns the items of the pagination in the output.
	 *
	 * @param string $output The output.
	 * @return string[] The items, as the type of each one followed by its text, and its link if it has one.
	 */
	private function get_items( $output ) {
		preg_match_all( '#<(a|span)([^>]*)>(.*?)</\1>#s', $output, $matches, PREG_SET_ORDER );

		$items = array();

		foreach ( $matches as $match ) {
			if ( false !== strpos( $match[2], 'current' ) ) {
				$type = 'current';
			} elseif ( false !== strpos( $match[2], 'dots' ) ) {
				$type = 'dots';
			} else {
				$type = 'link';
			}

			$item = $type . ':' . html_entity_decode( $match[3] );

			if ( preg_match( '#href="([^"]*)"#', $match[2], $href ) ) {
				$item .= ' ' . html_entity_decode( $href[1] );
			}

			$items[] = $item;
		}

		return $items;
	}

	/**
	 * Tests the numbers of the first page.
	 *
	 * @ticket 66573
	 */
	public function test_should_output_the_numbers_of_the_first_page() {
		$this->go_to( '/' );

		$output = $this->render();

		$this->assertStringStartsWith( '<div class="wp-block-query-pagination-numbers">', $output );
		$this->assertStringEndsWith( '</div>', $output );
		$this->assertSame(
			array( 'current:1', 'link:2 ?query-page=2', 'link:3 ?query-page=3' ),
			$this->get_items( $output )
		);
	}

	/**
	 * Tests the numbers of a page that is not the first, which add an argument to the first page.
	 *
	 * @ticket 66573
	 */
	public function test_should_output_the_numbers_of_a_page_that_is_not_the_first() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$this->assertSame(
			array( 'link:1 ?cst', 'current:2', 'link:3 ?query-page=3&cst' ),
			$this->get_items( $this->render() )
		);
	}

	/**
	 * Tests that the links to the previous and the next page are not output.
	 *
	 * @ticket 66573
	 */
	public function test_should_not_output_the_previous_and_next_links() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$output = $this->render();

		$this->assertStringNotContainsString( 'prev', $output );
		$this->assertStringNotContainsString( 'next', $output );
		$this->assertCount( 3, $this->get_items( $output ) );
	}

	/**
	 * Tests that a page that is not a number is the first page.
	 *
	 * @ticket 66573
	 *
	 * @dataProvider data_pages_of_the_first_page
	 *
	 * @param string $page The page of the request.
	 */
	public function test_should_use_the_first_page_for_a_page_that_is_not_valid( $page ) {
		$this->go_to( '/' );

		$_GET['query-page'] = $page;

		$items = $this->get_items( $this->render() );

		$this->assertSame( 'current:1', $items[0] );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_pages_of_the_first_page() {
		return array(
			'zero'  => array( '0' ),
			'text'  => array( 'abc' ),
			'empty' => array( '' ),
		);
	}

	/**
	 * Tests the page of the query that has an ID.
	 *
	 * @ticket 66573
	 */
	public function test_should_use_the_page_of_the_query_with_the_same_id() {
		$this->go_to( '/' );

		$_GET['query-4-page'] = '2';
		$_GET['query-page']   = '3';

		$this->assertSame(
			array( 'link:1 ?cst', 'current:2', 'link:3 ?query-4-page=3&cst' ),
			$this->get_items( $this->render( array(), array( 'queryId' => 4 ) ) )
		);
	}

	/**
	 * Tests that the page of the request for the page itself is kept in the links.
	 *
	 * @ticket 66573
	 */
	public function test_should_keep_the_page_of_the_request() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';
		$_GET['paged']      = '4';

		$this->assertSame(
			array( 'link:1 ?paged=4', 'current:2', 'link:3 ?query-page=3&paged=4' ),
			$this->get_items( $this->render() )
		);
	}

	/**
	 * Tests that nothing is output when there is one page or no page.
	 *
	 * @ticket 66573
	 *
	 * @dataProvider data_queries_with_one_page_at_most
	 *
	 * @param array $query The query of the context.
	 */
	public function test_should_output_nothing_for_one_page_at_most( $query ) {
		$this->go_to( '/' );

		$this->assertSame( '', $this->render( array(), array( 'query' => $query ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_queries_with_one_page_at_most() {
		return array(
			'one page'       => array( array( 'perPage' => 10 ) ),
			'no posts'       => array( array( 'search' => 'No post has this title' ) ),
			'a limit of one' => array( array( 'pages' => 1 ) ),
		);
	}

	/**
	 * Tests the number of pages of the query as the limit of the pages.
	 *
	 * @ticket 66573
	 *
	 * @dataProvider data_page_limits
	 *
	 * @param int $pages    The number of pages of the query.
	 * @param int $expected The number of items expected.
	 */
	public function test_should_limit_the_numbers_to_the_number_of_pages_of_the_query( $pages, $expected ) {
		$this->go_to( '/' );

		$output = $this->render( array(), array( 'query' => array( 'pages' => $pages ) ) );

		$this->assertCount( $expected, $this->get_items( $output ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_page_limits() {
		return array(
			'under the pages' => array( 2, 2 ),
			'the same'        => array( 3, 3 ),
			'over the pages'  => array( 10, 3 ),
			'no limit'        => array( 0, 3 ),
		);
	}

	/**
	 * Tests the number of links around the current page.
	 *
	 * @ticket 66573
	 *
	 * @dataProvider data_mid_sizes
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected items.
	 */
	public function test_should_use_the_mid_size( $attributes, $expected ) {
		$this->go_to( '/' );

		$_GET['query-page'] = '3';

		$output = $this->render( $attributes, array( 'query' => array( 'perPage' => 1 ) ) );

		$this->assertSame( $expected, $this->get_items( $output ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_mid_sizes() {
		return array(
			'zero'    => array(
				array( 'midSize' => 0 ),
				array( 'link:1 ?cst', 'dots:…', 'current:3', 'dots:…', 'link:5 ?query-page=5&cst' ),
			),
			'one'     => array(
				array( 'midSize' => 1 ),
				array( 'link:1 ?cst', 'link:2 ?query-page=2&cst', 'current:3', 'link:4 ?query-page=4&cst', 'link:5 ?query-page=5&cst' ),
			),
			'not set' => array(
				array(),
				array( 'link:1 ?cst', 'link:2 ?query-page=2&cst', 'current:3', 'link:4 ?query-page=4&cst', 'link:5 ?query-page=5&cst' ),
			),
		);
	}

	/**
	 * Tests the attributes of the enhanced pagination.
	 *
	 * @ticket 66573
	 */
	public function test_should_add_the_attributes_of_the_enhanced_pagination() {
		$this->go_to( '/' );

		$_GET['query-page'] = '2';

		$output = $this->render( array(), array( 'enhancedPagination' => true ) );

		$this->assertSame( 3, substr_count( $output, 'data-wp-key="index-' ), 'Each item should have a key.' );
		$this->assertStringContainsString( 'data-wp-key="index-0"', $output );
		$this->assertStringContainsString( 'data-wp-key="index-1"', $output );
		$this->assertStringContainsString( 'data-wp-key="index-2"', $output );
		$this->assertSame( 2, substr_count( $output, 'data-wp-on--click="core/query::actions.navigate"' ), 'Only the links should react to a click.' );
		$this->assertMatchesRegularExpression( '#<span data-wp-key="index-1" aria-current="page" class="page-numbers current">2</span>#', $output );
	}

	/**
	 * Tests that the attributes of the enhanced pagination are not added without it.
	 *
	 * @ticket 66573
	 */
	public function test_should_not_add_the_attributes_of_the_enhanced_pagination_without_it() {
		$this->go_to( '/' );

		$output = $this->render();

		$this->assertStringNotContainsString( 'data-wp-key', $output );
		$this->assertStringNotContainsString( 'data-wp-on--click', $output );
	}

	/**
	 * Tests the numbers of a query that inherits the query of the page.
	 *
	 * @ticket 66573
	 */
	public function test_should_use_the_query_of_the_page_when_the_block_inherits_it() {
		$this->go_to( '/?paged=2' );

		$_GET['query-page'] = '3';

		$output = $this->render( array(), array( 'query' => array( 'inherit' => true ) ) );
		$items  = $this->get_items( $output );

		$this->assertCount( 3, $items );
		$this->assertMatchesRegularExpression( '#^link:1 #', $items[0] );
		$this->assertSame( 'current:2', $items[1], 'The current page should be the page of the page.' );
		$this->assertMatchesRegularExpression( '#^link:3 .*(paged=3|/page/3/)#', $items[2] );
		$this->assertStringStartsWith( '<div class="wp-block-query-pagination-numbers">', $output );
	}

	/**
	 * Tests the number of pages of the query as the limit of the pages of a query that inherits the query of the page.
	 *
	 * @ticket 66573
	 *
	 * @dataProvider data_page_limits
	 *
	 * @param int $pages    The number of pages of the query.
	 * @param int $expected The number of items expected.
	 */
	public function test_should_limit_the_numbers_of_the_query_of_the_page( $pages, $expected ) {
		$this->go_to( '/?paged=2' );

		$output = $this->render(
			array(),
			array(
				'query' => array(
					'inherit' => true,
					'pages'   => $pages,
				),
			)
		);

		$this->assertCount( $expected, $this->get_items( $output ) );
	}

	/**
	 * Tests the number of links around the current page of a query that inherits the query of the page.
	 *
	 * @ticket 66573
	 *
	 * @dataProvider data_inherited_mid_sizes
	 *
	 * @param array    $attributes The attributes of the block.
	 * @param string[] $expected   The expected items, without the links.
	 */
	public function test_should_use_the_mid_size_for_the_query_of_the_page( $attributes, $expected ) {
		update_option( 'posts_per_page', 1 );
		$this->go_to( '/?paged=3' );

		$output = $this->render( $attributes, array( 'query' => array( 'inherit' => true ) ) );
		$items  = array_map(
			static function ( $item ) {
				return strtok( $item, ' ' );
			},
			$this->get_items( $output )
		);

		$this->assertSame( $expected, $items );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_inherited_mid_sizes() {
		return array(
			'zero'    => array( array( 'midSize' => 0 ), array( 'link:1', 'dots:…', 'current:3', 'dots:…', 'link:5' ) ),
			'one'     => array( array( 'midSize' => 1 ), array( 'link:1', 'link:2', 'current:3', 'link:4', 'link:5' ) ),
			'not set' => array( array(), array( 'link:1', 'link:2', 'current:3', 'link:4', 'link:5' ) ),
		);
	}

	/**
	 * Tests that the main query is the same after the output of a custom query.
	 *
	 * @ticket 66573
	 */
	public function test_should_restore_the_main_query() {
		$this->go_to( '/' );

		$query = $GLOBALS['wp_query'];

		$this->render();

		$this->assertSame( $query, $GLOBALS['wp_query'] );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66573
	 *
	 * @covers ::register_block_core_query_pagination_numbers
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/query-pagination-numbers' );

		if ( $original_block ) {
			unregister_block_type( 'core/query-pagination-numbers' );
		}

		try {
			register_block_core_query_pagination_numbers();
			$block_type = $registry->get_registered( 'core/query-pagination-numbers' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_query_pagination_numbers', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/query-pagination-numbers' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
