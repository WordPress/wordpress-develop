<?php
/**
 * Tests for the server-side rendering of the Query Total block.
 *
 * @package WordPress
 * @subpackage Blocks
 *
 * @group blocks
 *
 * @covers ::render_block_core_query_total
 */
class Tests_Blocks_RenderQueryTotal extends WP_UnitTestCase {

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

	public function set_up() {
		parent::set_up();

		$this->original_block_to_render     = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = null;
		$this->original_get                 = $_GET;
	}

	public function tear_down() {
		WP_Block_Supports::$block_to_render = $this->original_block_to_render;
		$_GET                               = $this->original_get;

		parent::tear_down();
	}

	/**
	 * Creates posts with a title that a query can search for.
	 *
	 * @param int $count Number of posts.
	 */
	private function create_posts( $count ) {
		self::factory()->post->create_many( $count, array( 'post_title' => 'Total test post' ) );
	}

	/**
	 * Renders the block with a query of its own.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $query      Optional. The query of the block. Default is five posts per page.
	 * @param array $context    Optional. Other context of the block. Default none.
	 * @return string The rendered block.
	 */
	private function render( $attributes, $query = array(), $context = array() ) {
		$context['query'] = array_merge(
			array(
				'perPage'  => 5,
				'postType' => 'post',
				'search'   => 'Total test post',
				'inherit'  => false,
			),
			$query
		);

		return $this->render_with_context( $attributes, $context );
	}

	/**
	 * Renders the block with a context.
	 *
	 * @param array $attributes Attributes of the block.
	 * @param array $context    Context of the block.
	 * @return string The rendered block.
	 */
	private function render_with_context( $attributes, $context ) {
		$block = new WP_Block(
			array(
				'blockName'    => 'core/query-total',
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			),
			$context
		);

		return render_block_core_query_total( $attributes, '', $block );
	}

	/**
	 * Tests the total of the results.
	 *
	 * @ticket 66568
	 *
	 * @dataProvider data_totals
	 *
	 * @param int    $posts    Number of posts.
	 * @param string $expected Expected text.
	 */
	public function test_should_render_the_total_of_the_results( $posts, $expected ) {
		$this->create_posts( $posts );

		$this->assertSame( "<div >{$expected}</div>", $this->render( array( 'displayType' => 'total-results' ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_totals() {
		return array(
			'no results'       => array( 0, '0 results found' ),
			'one result'       => array( 1, '1 result found' ),
			'two results'      => array( 2, '2 results found' ),
			'more than a page' => array( 7, '7 results found' ),
		);
	}

	/**
	 * Tests that the total is the default display type.
	 *
	 * @ticket 66568
	 */
	public function test_should_render_the_total_for_a_display_type_that_is_not_known() {
		$this->create_posts( 3 );

		$this->assertSame( '<div >3 results found</div>', $this->render( array( 'displayType' => 'something-else' ) ) );
	}

	/**
	 * Tests the range of the results.
	 *
	 * @ticket 66568
	 *
	 * @dataProvider data_ranges
	 *
	 * @param int    $posts    Number of posts.
	 * @param int    $page     Page of the results.
	 * @param string $expected Expected text.
	 */
	public function test_should_render_the_range_of_the_results( $posts, $page, $expected ) {
		$this->create_posts( $posts );

		$_GET['query-page'] = $page;

		$this->assertSame( "<div >{$expected}</div>", $this->render( array( 'displayType' => 'range-display' ), array( 'perPage' => 3 ) ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public static function data_ranges() {
		return array(
			'the first page'             => array( 7, 1, 'Displaying 1 – 3 of 7' ),
			'the second page'            => array( 7, 2, 'Displaying 4 – 6 of 7' ),
			'the last page, partly full' => array( 7, 3, 'Displaying 7 of 7' ),
			'a single result'            => array( 1, 1, 'Displaying 1 of 1' ),
			'no results'                 => array( 0, 1, 'Displaying 0 of 0' ),
		);
	}

	/**
	 * Tests the page of the query that has an ID.
	 *
	 * @ticket 66568
	 */
	public function test_should_use_the_page_of_the_query_with_the_same_id() {
		$this->create_posts( 7 );

		$_GET['query-5-page'] = 2;
		$_GET['query-page']   = 3;

		$this->assertSame(
			'<div >Displaying 4 – 6 of 7</div>',
			$this->render( array( 'displayType' => 'range-display' ), array( 'perPage' => 3 ), array( 'queryId' => 5 ) )
		);
	}

	/**
	 * Tests the totals of the query of the page.
	 *
	 * @ticket 66568
	 */
	public function test_should_use_the_query_of_the_page_when_the_block_inherits_it() {
		$this->create_posts( 5 );
		update_option( 'posts_per_page', 2 );

		$this->go_to( '/?paged=2' );

		$context = array(
			'query' => array(
				'inherit' => true,
				'perPage' => 50,
			),
		);

		$this->assertSame( '<div >Displaying 3 – 4 of 5</div>', $this->render_with_context( array( 'displayType' => 'range-display' ), $context ), 'The page and the number of posts of the page should be used.' );
		$this->assertSame( '<div >5 results found</div>', $this->render_with_context( array( 'displayType' => 'total-results' ), $context ) );
	}

	/**
	 * Tests the first page for the query of the page.
	 *
	 * @ticket 66568
	 */
	public function test_should_use_the_first_page_for_the_query_of_the_page_without_a_page() {
		$this->create_posts( 5 );
		update_option( 'posts_per_page', 2 );

		$this->go_to( '/' );

		$context = array( 'query' => array( 'inherit' => true ) );

		$this->assertSame( '<div >Displaying 1 – 2 of 5</div>', $this->render_with_context( array( 'displayType' => 'range-display' ), $context ) );
	}

	/**
	 * Tests that the block is registered with the render callback.
	 *
	 * @ticket 66568
	 *
	 * @covers ::register_block_core_query_total
	 */
	public function test_should_register_the_block_with_the_render_callback() {
		$registry       = WP_Block_Type_Registry::get_instance();
		$original_block = $registry->get_registered( 'core/query-total' );

		if ( $original_block ) {
			unregister_block_type( 'core/query-total' );
		}

		try {
			register_block_core_query_total();
			$block_type = $registry->get_registered( 'core/query-total' );

			$this->assertInstanceOf( WP_Block_Type::class, $block_type );
			$this->assertSame( 'render_block_core_query_total', $block_type->render_callback );
		} finally {
			unregister_block_type( 'core/query-total' );

			if ( $original_block ) {
				$registry->register( $original_block );
			}
		}
	}
}
